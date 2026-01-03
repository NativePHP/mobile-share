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
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val title = parameters["title"] as? String ?: ""
            val message = parameters["message"] as? String ?: ""
            val filePath = parameters["filePath"] as? String

            Log.d("ShareFunctions.File", "Share requested - title: $title, message: $message, filePath: $filePath")

            try {
                cleanupOldShareFiles(context)

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
