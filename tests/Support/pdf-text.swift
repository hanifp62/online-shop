// Test-only PDF text extractor using macOS PDFKit (system framework, no added dependency).
// Prints each page's text separated by form feed so tests can assert pagination.
import Foundation
import PDFKit

guard CommandLine.arguments.count == 2,
      let document = PDFDocument(url: URL(fileURLWithPath: CommandLine.arguments[1])) else {
    FileHandle.standardError.write("unreadable pdf\n".data(using: .utf8)!)
    exit(2)
}

var pages: [String] = []
for index in 0..<document.pageCount {
    pages.append(document.page(at: index)?.string ?? "")
}
print(pages.joined(separator: "\u{0C}"), terminator: "")
