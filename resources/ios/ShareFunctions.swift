import Foundation
import UIKit

// MARK: - Share Function Namespace

/// Functions related to native share sheet
/// Namespace: "Share.*"
enum ShareFunctions {

    // MARK: - Path confinement
    //
    // Share.File is exported in the JS bridge, so a web view page can call it
    // through `/_native/api/call` with a `filePath` of its choosing. Handed an
    // arbitrary readable path, `UIActivityViewController` will vend any file
    // the app can read — `.env`, the SQLite database — out through the share
    // sheet. That is a file-exfil primitive: user-gated by the sheet (the user
    // must pick a target), but still a read-out of a named sandbox file.
    //
    // So a shared file path is confined, not just the method gated: it must
    // resolve inside a scratch area (temp or caches) or the app's own storage
    // (`storage_path('app')`). Secrets like `.env`, the database, `public/`,
    // `bootstrap/cache` and `storage/framework` sit outside those roots, so a
    // page cannot name them. The legitimate use — sharing a file the app
    // captured into temp/caches or saved under `storage/app` — stays inside.
    //
    // The path is canonicalised first (symlinks and `..` resolved) and only
    // then prefix-checked, so neither `..` nor a symlink can walk out of a
    // root. URLs (http/https/ftp) are not file paths and are not confined.

    /// A path with every symlink and `..`/`.` resolved. A non-existent path is
    /// resolved through its existing parent, so the check still sees where it
    /// would land.
    private static func canonical(_ path: String) -> String {
        let fileManager = FileManager.default
        let url = URL(fileURLWithPath: path)

        if fileManager.fileExists(atPath: url.path) {
            return url.resolvingSymlinksInPath().standardizedFileURL.path
        }

        let parent = url.deletingLastPathComponent().resolvingSymlinksInPath().standardizedFileURL
        return parent.appendingPathComponent(url.lastPathComponent).standardizedFileURL.path
    }

    /// True when `path` is `root` itself or sits beneath it. Both must already
    /// be canonical, so a prefix match is a real containment.
    private static func isInside(_ path: String, _ root: String) -> Bool {
        !root.isEmpty && (path == root || path.hasPrefix(root + "/"))
    }

    /// The app's own storage area — where `storage_path('app')` resolves.
    ///
    /// On iOS `storage_path()` is NOT under the extracted app. Core points
    /// Laravel at Application Support via `LARAVEL_STORAGE_PATH` (set in
    /// `NativePHPApp.setupEnvironment`), which the framework reads in
    /// `Application::storagePath()`. Derive the root from that same env so a
    /// legitimate `storage_path('app')` file is accepted; if it is somehow
    /// unset, return "" so the root is simply dropped (fail closed — temp and
    /// caches still work, storage sharing is refused rather than wide open).
    private static var storageAppRoot: String {
        if let raw = getenv("LARAVEL_STORAGE_PATH") {
            let storage = String(cString: raw)
            if !storage.isEmpty {
                return canonical(storage + "/app")
            }
        }

        return ""
    }

    /// The roots a shared file may come from: the scratch areas plus storage.
    private static var shareableRoots: [String] {
        var roots = [canonical(NSTemporaryDirectory()), storageAppRoot]
        if let caches = FileManager.default.urls(for: .cachesDirectory, in: .userDomainMask).first {
            roots.append(canonical(caches.path))
        }
        return roots.filter { !$0.isEmpty }
    }

    /// Refuse a share whose file path falls outside the allowed roots. Returns
    /// an error payload to hand straight back, or nil to proceed.
    private static func confinementError(path: String) -> [String: Any]? {
        let real = canonical(path)

        guard shareableRoots.contains(where: { isInside(real, $0) }) else {
            print("Share refused: file outside the allowed roots")
            return ["error": "File path is not allowed"]
        }

        return nil
    }

    // MARK: - Share.Url

    /// Show the native share sheet for URLs
    /// Parameters:
    ///   - title: (optional) string - Share dialog title / subject
    ///   - text: (optional) string - Text message to share with the URL
    ///   - url: string - URL to share
    class Url: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            let title = parameters["title"] as? String ?? ""
            let text = parameters["text"] as? String ?? ""
            let url = parameters["url"] as? String ?? ""

            print("Share URL requested - title: '\(title)', url: '\(url)'")

            guard !url.isEmpty else {
                print("URL parameter is required")
                return ["error": "URL parameter is required"]
            }

            var shareItems: [Any] = []

            if let urlObj = URL(string: url) {
                shareItems.append(urlObj)
            } else {
                shareItems.append(url)
            }

            if !title.isEmpty {
                shareItems.append(title)
            }

            if !text.isEmpty {
                shareItems.append(text)
            }

            DispatchQueue.main.async {
                guard let windowScene = UIApplication.shared.connectedScenes
                    .compactMap({ $0 as? UIWindowScene })
                    .first(where: { $0.activationState == .foregroundActive }),
                      let rootVC = windowScene.windows
                    .first(where: { $0.isKeyWindow })?
                    .rootViewController else {
                    print("Could not find root view controller")
                    return
                }

                let activityVC = UIActivityViewController(
                    activityItems: shareItems,
                    applicationActivities: nil
                )

                if let popover = activityVC.popoverPresentationController {
                    popover.sourceView = rootVC.view
                    popover.permittedArrowDirections = .any
                    popover.sourceRect = CGRect(x: rootVC.view.bounds.midX, y: rootVC.view.bounds.midY, width: 0, height: 0)
                }

                rootVC.present(activityVC, animated: true) {
                    print("Share sheet presented successfully")
                }
            }

            return [:]
        }
    }

    // MARK: - Share.File

    /// Show the native share sheet for files
    /// Parameters:
    ///   - title: (optional) string - Share dialog title / subject
    ///   - message: (optional) string - Text message to share
    ///   - filePath: (optional) string - Absolute path to file to share
    class File: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            let title = parameters["title"] as? String ?? ""
            let message = parameters["message"] as? String ?? ""
            let filePath = parameters["filePath"] as? String

            print("Share requested - title: '\(title)', message: '\(message)', filePath: '\(filePath ?? "nil")'")

            var shareItems: [Any] = []

            if let filePath = filePath, !filePath.isEmpty {
                if isURL(filePath) {
                    if let url = URL(string: filePath) {
                        shareItems.append(url)
                    } else {
                        shareItems.append(filePath)
                    }
                } else {
                    if let refusal = ShareFunctions.confinementError(path: filePath) {
                        return refusal
                    }

                    let fileURL = URL(fileURLWithPath: filePath)

                    if FileManager.default.fileExists(atPath: fileURL.path) {
                        shareItems.append(fileURL)
                        print("Added file to share items: \(fileURL.lastPathComponent)")
                    } else {
                        print("File not found at path: \(filePath)")
                        if !message.isEmpty {
                            shareItems.append("\(message)\n\n\(filePath)")
                        } else {
                            shareItems.append(filePath)
                        }
                    }
                }
            }

            if !title.isEmpty && !shareItems.contains(where: { $0 as? String == title }) {
                shareItems.append(title)
            }

            if !message.isEmpty && !shareItems.contains(where: { $0 as? String == message }) {
                shareItems.append(message)
            }

            if shareItems.isEmpty {
                print("No items to share")
                return ["error": "No items to share"]
            }

            print("Sharing \(shareItems.count) item(s)")

            DispatchQueue.main.async {
                guard let windowScene = UIApplication.shared.connectedScenes
                    .compactMap({ $0 as? UIWindowScene })
                    .first(where: { $0.activationState == .foregroundActive }),
                      let rootVC = windowScene.windows
                    .first(where: { $0.isKeyWindow })?
                    .rootViewController else {
                    print("Could not find root view controller")
                    return
                }

                let activityVC = UIActivityViewController(
                    activityItems: shareItems,
                    applicationActivities: nil
                )

                if let popover = activityVC.popoverPresentationController {
                    popover.sourceView = rootVC.view
                    popover.permittedArrowDirections = .any
                    popover.sourceRect = CGRect(x: rootVC.view.bounds.midX, y: rootVC.view.bounds.midY, width: 0, height: 0)
                }

                rootVC.present(activityVC, animated: true) {
                    print("Share sheet presented successfully")
                }
            }

            return [:]
        }

        private func isURL(_ string: String) -> Bool {
            let lowercased = string.lowercased()
            return lowercased.hasPrefix("http://") ||
                   lowercased.hasPrefix("https://") ||
                   lowercased.hasPrefix("ftp://")
        }
    }
}
