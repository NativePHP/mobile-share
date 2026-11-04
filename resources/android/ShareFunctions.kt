package com.nativephp.share

import android.content.Context
import android.content.Intent
import android.util.Log
import androidx.core.content.FileProvider
import com.nativephp.mobile.bridge.BridgeFunction
import java.io.File

/**
 * Functions related to native share sheet
 * Namespace: "Share.*"
 */
object ShareFunctions {

    /**
     * Show the native share sheet for URLs
     * Parameters:
     *   - title: (optional) string - Share dialog title / subject
     *   - text: (optional) string - Text message to share with the URL
     *   - url: string - URL to share
     */
    class Url(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val title = parameters["title"] as? String ?: ""
            val text = parameters["text"] as? String ?: ""
            val url = parameters["url"] as? String ?: ""

            Log.d("ShareFunctions.Url", "Share URL requested - title: $title, url: $url")

            if (url.isEmpty()) {
                Log.e("ShareFunctions.Url", "URL parameter is required")
                return mapOf("error" to "URL parameter is required")
            }

            try {
                val intent = Intent(Intent.ACTION_SEND).apply {
                    type = "text/plain"

                    if (title.isNotEmpty()) {
                        putExtra(Intent.EXTRA_SUBJECT, title)
                    }

                    val shareText = if (text.isNotEmpty()) {
                        "$text\n\n$url"
                    } else {
                        url
                    }
                    putExtra(Intent.EXTRA_TEXT, shareText)
                }

                val chooser = Intent.createChooser(intent, title.ifEmpty { "Share" })
                chooser.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                context.startActivity(chooser)
                Log.d("ShareFunctions.Url", "Share sheet opened")

            } catch (e: Exception) {
                Log.e("ShareFunctions.Url", "Error launching share sheet: ${e.message}", e)
                return mapOf("error" to (e.message ?: "Unknown error"))
            }

            return emptyMap()
        }
    }

    /**
     * Show the native share sheet for files
     * Parameters:
     *   - title: (optional) string - Share dialog title / subject
     *   - message: (optional) string - Text message to share
     *   - filePath: (optional) string - Absolute path to file to share
     */
    class File(private val context: Context) : BridgeFunction {

        // Path confinement
        //
        // Share.File is exported in the JS bridge, so a web view page can call
        // it through `/_native/api/call` with a `filePath` of its choosing.
        // Handed an arbitrary readable path, the share intent will vend any
        // file the app can read — `.env`, the SQLite database — out through the
        // share sheet. That is a file-exfil primitive: user-gated by the sheet
        // (the user must pick a target), but still a read-out of a named
        // sandbox file. The non-app_storage branch below leans on FileProvider,
        // which narrows but does not confine the path.
        //
        // So a shared file path is confined, not just the method gated: it must
        // resolve inside a scratch area (the app cache) or the app's own
        // storage (`storage_path('app')`). Secrets like `.env`, the database,
        // `public/`, `bootstrap/cache` and `storage/framework` sit outside
        // those roots, so a page cannot name them. The legitimate use — sharing
        // a file the app captured into the cache or saved under `storage/app` —
        // stays inside.
        //
        // Every path is canonicalised first (canonicalPath resolves symlinks
        // and `..`) and only then prefix-checked, so neither `..` nor a symlink
        // can walk out of a root. URLs (http/https/ftp) are not file paths and
        // are not confined.
        //
        // These helpers live inside File (not on the object) so the unqualified
        // `File(...)` constructor keeps resolving to java.io.File, as it does
        // across the rest of this class. The classifier name `File` on its own,
        // though, binds to this enclosing class, so the one static-member access
        // (`separator`) is spelled out as java.io.File.

        /** A path with symlinks and `..`/`.` resolved; the literal path on failure. */
        private fun canonical(path: String): String =
            try { File(path).canonicalPath } catch (e: Exception) { File(path).absolutePath }

        /** True when [path] is [root] itself or sits beneath it; both canonical. */
        private fun isInside(path: String, root: String): Boolean =
            path == root || path.startsWith(root + java.io.File.separator)

        /**
         * The app's own storage area — where `storage_path('app')` resolves.
         *
         * `storage_path()` is NOT under the Laravel app tree. `LaravelEnvironment`
         * points Laravel at `getDir("storage")/persisted_data/storage` via
         * `LARAVEL_STORAGE_PATH`, deliberately outside `…/laravel` so it
         * survives an OTA that swaps the extract. This mirrors that exact path,
         * so a legitimate `storage_path('app')` file is accepted.
         */
        private fun storageAppRoot(): String =
            canonical(File(context.getDir("storage", Context.MODE_PRIVATE), "persisted_data/storage/app").path)

        /** The roots a shared file may come from: the cache areas plus storage. */
        private fun shareableRoots(): List<String> {
            val roots = mutableListOf(canonical(context.cacheDir.path))
            context.externalCacheDir?.let { roots.add(canonical(it.path)) }
            roots.add(storageAppRoot())
            return roots
        }

        /**
         * Refuse a share whose file path falls outside the allowed roots.
         * Returns an error payload to hand straight back, or null to proceed.
         */
        private fun confinementError(path: String): Map<String, Any>? {
            val real = canonical(path)

            if (shareableRoots().none { isInside(real, it) }) {
                Log.w("ShareFunctions.File", "Share refused: file outside the allowed roots")
                return mapOf("error" to "File path is not allowed")
            }

            return null
        }

        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val title = parameters["title"] as? String ?: ""
            val message = parameters["message"] as? String ?: ""
            val filePath = parameters["filePath"] as? String

            Log.d("ShareFunctions.File", "Share requested - title: $title, message: $message, filePath: $filePath")

            try {
                cleanupOldShareFiles(context)

                if (!filePath.isNullOrEmpty() && !isUrl(filePath)) {
                    confinementError(filePath)?.let { return it }
                }

                val intent = Intent(Intent.ACTION_SEND).apply {
                    if (!filePath.isNullOrEmpty()) {
                        val file = File(filePath)

                        if (file.exists()) {
                            val fileToShare = if (file.absolutePath.contains("app_storage")) {
                                val shareDir = File(context.cacheDir, "share")
                                shareDir.mkdirs()

                                val tempFile = File(shareDir, file.name)
                                file.inputStream().use { input ->
                                    tempFile.outputStream().use { output ->
                                        input.copyTo(output)
                                    }
                                }
                                tempFile
                            } else {
                                file
                            }

                            val uri = FileProvider.getUriForFile(
                                context,
                                "${context.packageName}.fileprovider",
                                fileToShare
                            )

                            val mimeType = getMimeTypeFromExtension(file.extension)
                            type = mimeType

                            putExtra(Intent.EXTRA_STREAM, uri)
                            addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)

                            if (title.isNotEmpty()) {
                                putExtra(Intent.EXTRA_SUBJECT, title)
                            }
                            if (message.isNotEmpty()) {
                                putExtra(Intent.EXTRA_TEXT, message)
                            }

                            Log.d("ShareFunctions.File", "Sharing file: ${file.name} ($mimeType)")
                        } else {
                            type = "text/plain"
                            putExtra(Intent.EXTRA_SUBJECT, title)

                            val textToShare = if (isUrl(filePath)) {
                                if (message.isNotEmpty()) "$message\n\n$filePath" else filePath
                            } else {
                                message
                            }
                            putExtra(Intent.EXTRA_TEXT, textToShare)
                        }
                    } else {
                        type = "text/plain"
                        putExtra(Intent.EXTRA_SUBJECT, title)
                        putExtra(Intent.EXTRA_TEXT, message)
                    }
                }

                val chooser = Intent.createChooser(intent, title)
                chooser.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                context.startActivity(chooser)
                Log.d("ShareFunctions.File", "Share sheet opened")

            } catch (e: Exception) {
                Log.e("ShareFunctions.File", "Error launching share sheet: ${e.message}", e)
                return mapOf("error" to (e.message ?: "Unknown error"))
            }

            return emptyMap()
        }

        private fun getMimeTypeFromExtension(extension: String): String {
            return when (extension.lowercase()) {
                "m4a", "aac" -> "audio/mp4"
                "mp3" -> "audio/mpeg"
                "wav" -> "audio/wav"
                "ogg" -> "audio/ogg"
                "flac" -> "audio/flac"
                "mp4", "m4v" -> "video/mp4"
                "mov" -> "video/quicktime"
                "avi" -> "video/x-msvideo"
                "mkv" -> "video/x-matroska"
                "webm" -> "video/webm"
                "jpg", "jpeg" -> "image/jpeg"
                "png" -> "image/png"
                "gif" -> "image/gif"
                "webp" -> "image/webp"
                "pdf" -> "application/pdf"
                "txt" -> "text/plain"
                else -> "*/*"
            }
        }

        private fun isUrl(path: String): Boolean {
            return path.startsWith("http://", ignoreCase = true) ||
                   path.startsWith("https://", ignoreCase = true) ||
                   path.startsWith("ftp://", ignoreCase = true)
        }

        private fun cleanupOldShareFiles(context: Context) {
            try {
                val shareDir = File(context.cacheDir, "share")
                if (!shareDir.exists()) return

                val oneHourAgo = System.currentTimeMillis() - (60 * 60 * 1000)
                shareDir.listFiles()?.forEach { file ->
                    if (file.lastModified() < oneHourAgo) {
                        file.delete()
                    }
                }
            } catch (e: Exception) {
                Log.e("ShareFunctions.File", "Error cleaning up old share files: ${e.message}", e)
            }
        }
    }
}
