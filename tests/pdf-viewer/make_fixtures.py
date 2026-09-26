"""Build the PDFs the viewer tests load.

  python3 make_fixtures.py <output-dir>

leaders-guide.pdf  12 pages: roman-numeral front matter (i-iii) then 1-9, an outline,
                   document metadata, searchable text, a landscape page (printed p. 5)
                   and an image-only "scanned" page (printed p. 7).
scan-only.pdf      3 image-only pages with no text layer, like an un-OCR'd scan.
"""
import io
import sys
from pathlib import Path

from PIL import Image, ImageDraw
from reportlab.lib.pagesizes import letter, landscape
from reportlab.lib.utils import ImageReader
from reportlab.pdfgen import canvas


def scan_image(label):
    """A faded grey page image with no text layer, like an old scan."""
    img = Image.new("L", (850, 1100), 236)
    draw = ImageDraw.Draw(img)
    draw.rectangle([60, 60, 790, 1040], outline=190, width=4)
    for y in range(160, 1000, 40):
        draw.line([100, y, 750, y], fill=200, width=6)
    draw.text((100, 100), label, fill=150)
    buf = io.BytesIO()
    img.save(buf, "PNG")
    buf.seek(0)
    return ImageReader(buf)


def leaders_guide(path):
    c = canvas.Canvas(str(path), pagesize=letter)
    c.setTitle("Camp Tahquitz Leaders Guide")
    c.setAuthor("Test Council, Boy Scouts of America")
    c.setSubject("Summer camp program")
    c.setKeywords("camp, waterfront, Order of the Arrow")
    c.setCreator("Scouting Memories test fixture")

    def body(lines, y=680):
        c.setFont("Helvetica", 12)
        for line in lines:
            c.drawString(72, y, line)
            y -= 18

    # PDF pages 1-3: front matter labelled i, ii, iii
    c.addPageLabel(0, style="ROMAN_LOWER")
    for n in range(3):
        c.setFont("Helvetica-Bold", 20)
        c.drawString(72, 720, ["Title page", "Foreword", "Contents"][n])
        body(["Front matter page %d." % (n + 1)])
        if n == 0:
            c.bookmarkPage("front")
            c.addOutlineEntry("Front matter", "front", level=0)
        c.showPage()

    # PDF pages 4-12: labelled 1-9
    c.addPageLabel(3, style="ARABIC", start=1)
    for n in range(4, 13):
        printed = n - 3
        if printed == 5:
            c.setPageSize(landscape(letter))
            c.setFont("Helvetica-Bold", 20)
            c.drawString(72, 540, "Camp map (fold-out)")
            body(["Landscape fold-out map of the reservation."], y=500)
            c.showPage()
            c.setPageSize(letter)
            continue
        if printed == 7:
            c.drawImage(scan_image("scanned letter"), 0, 0, width=612, height=792)
            c.showPage()
            continue
        c.setFont("Helvetica-Bold", 20)
        c.drawString(72, 720, "Chapter page %d" % printed)
        lines = ["Printed page %d of the leaders guide." % printed]
        if printed == 1:
            c.bookmarkPage("ch1")
            c.addOutlineEntry("Chapter 1: Camp history", "ch1", level=0)
            lines += ["Camp Tahquitz opened in 1952 on the shore of the lake.",
                      "The Order of the Arrow lodge met here every summer."]
        if printed == 3:
            c.bookmarkPage("water")
            c.addOutlineEntry("Waterfront", "water", level=1)
            lines += ["Waterfront rules: every Scout swims with a buddy.",
                      "Order of the Arrow members staffed the waterfront."]
        if printed == 8:
            lines += ["Closing campfire. The ORDER OF THE ARROW tapout ceremony."]
        body(lines)
        c.showPage()
    c.showOutline()
    c.save()


def scan_only(path):
    c = canvas.Canvas(str(path), pagesize=letter)
    c.setTitle("Untranscribed scan")
    for n in range(3):
        c.drawImage(scan_image("page %d" % (n + 1)), 0, 0, width=612, height=792)
        c.showPage()
    c.save()


if __name__ == "__main__":
    out = Path(sys.argv[1] if len(sys.argv) > 1 else "fixtures")
    out.mkdir(parents=True, exist_ok=True)
    leaders_guide(out / "leaders-guide.pdf")
    scan_only(out / "scan-only.pdf")
    print("wrote fixtures to", out)
