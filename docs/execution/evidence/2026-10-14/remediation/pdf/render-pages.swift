import AppKit
import PDFKit
import Foundation
// Usage: render-pages <in.pdf> <out-dir>; rasterizes each page through macOS PDFKit at 2x.
guard CommandLine.arguments.count == 3 else { exit(1) }
let url = URL(fileURLWithPath: CommandLine.arguments[1])
let outDir = CommandLine.arguments[2]
let tmp = URL(fileURLWithPath: NSTemporaryDirectory()).resolvingSymlinksInPath().path
let outPath = URL(fileURLWithPath: outDir).resolvingSymlinksInPath().path
guard outPath.hasPrefix(tmp + "/") || outPath == tmp else {
    fputs("FAIL: outDir must reside under temporary directory\n", stderr)
    exit(1)
}
guard let document = PDFDocument(url: url) else { exit(2) }
for index in 0..<document.pageCount {
    guard let page = document.page(at: index) else { exit(3) }
    let bounds = page.bounds(for: .mediaBox)
    let scale: CGFloat = 2
    let image = NSImage(size: NSSize(width: bounds.width * scale, height: bounds.height * scale))
    image.lockFocus()
    NSColor.white.setFill()
    NSRect(origin: .zero, size: image.size).fill()
    let context = NSGraphicsContext.current!.cgContext
    context.scaleBy(x: scale, y: scale)
    page.draw(with: .mediaBox, to: context)
    image.unlockFocus()
    let rep = NSBitmapImageRep(data: image.tiffRepresentation!)!
    try! rep.representation(using: .png, properties: [:])!.write(to: URL(fileURLWithPath: "\(outDir)/page-\(index + 1).png"))
}
print("pages=\(document.pageCount)")
