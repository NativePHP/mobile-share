import Foundation
import UIKit

// MARK: - Share Function Namespace

/// Functions related to native share sheet
/// Namespace: "Share.*"
enum ShareFunctions {

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

            let shareText = text.isEmpty ? url : "\(text)\n\n\(url)"
            let shareItems: [Any] = [ShareTextItemSource(text: shareText, subject: title)]

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
            var shouldAppendTitle = true
            var shouldAppendMessage = true

            if let filePath = filePath, !filePath.isEmpty {
                if isURL(filePath) {
                    let textToShare = message.isEmpty ? filePath : "\(message)\n\n\(filePath)"
                    shareItems.append(ShareTextItemSource(text: textToShare, subject: title))
                    shouldAppendTitle = false
                    shouldAppendMessage = false
                } else {
                    let fileURL = URL(fileURLWithPath: filePath)

                    if FileManager.default.fileExists(atPath: fileURL.path) {
                        shareItems.append(fileURL)
                        print("Added file to share items: \(fileURL.lastPathComponent)")
                    } else {
                        print("File not found at path: \(filePath)")
                        let textToShare = message.isEmpty ? filePath : "\(message)\n\n\(filePath)"
                        shareItems.append(ShareTextItemSource(text: textToShare, subject: title))
                        shouldAppendTitle = false
                        shouldAppendMessage = false
                    }
                }
            }

            if shouldAppendTitle && !title.isEmpty && !shareItems.contains(where: { $0 as? String == title }) {
                shareItems.append(title)
            }

            if shouldAppendMessage && !message.isEmpty && !shareItems.contains(where: { $0 as? String == message }) {
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

private final class ShareTextItemSource: NSObject, UIActivityItemSource {
    private let text: String
    private let subject: String?

    init(text: String, subject: String) {
        self.text = text
        self.subject = subject.isEmpty ? nil : subject
    }

    func activityViewControllerPlaceholderItem(_ activityViewController: UIActivityViewController) -> Any {
        return text
    }

    func activityViewController(_ activityViewController: UIActivityViewController, itemForActivityType activityType: UIActivity.ActivityType?) -> Any? {
        return text
    }

    func activityViewController(_ activityViewController: UIActivityViewController, subjectForActivityType activityType: UIActivity.ActivityType?) -> String {
        return subject ?? ""
    }
}
