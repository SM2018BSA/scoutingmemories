# The archive's fields

TIMELINE fields (what and where the document is about):

- state: a list of 2-letter codes from the index's state list, e.g. ["NY"]. States
  include territories and overseas areas (PR, GU, PCZ, FE...). A document can concern
  more than one state; most concern one.
- council: the slug of ONE council from the candidates, e.g. "general_herkimer_400".
  Slugs are the index's Name_Number form; copy them exactly.
- camp: the slug of ONE camp from the candidates, e.g. "camp_russell_385", or null when
  the document is not about a particular camp.
- lodge: the slug of ONE Order of the Arrow lodge from the candidates, e.g.
  "kamargo_lodge_294", or null. A lodge is an OA honor society unit, not a camp; words
  like "Lodge", "Order of the Arrow", "OA", "Vigil", "Brotherhood", "Ordeal" point to one.
- start_date and end_date: 4-digit years, the span the document's CONTENT covers. A
  single-year document has start_date = end_date. A 1952 camp season newsletter is
  "1952"/"1952"; a council history covering 1925-1960 is "1925"/"1960".

Category: exactly one of History, Memorabilia, Photographs, Museums, Oral History,
Movies. Newsletters, yearbooks, minutes, letters and histories are History. Patches,
badges, cards and physical objects (or catalogs of them) are Memorabilia. Photo pages
and albums are Photographs.

META fields (Dublin-Core-style description of the digital copy; all plain strings or null):

- publisher_of_digital: who digitized or published the digital copy, when the document
  says so, e.g. "Utica College - Center for Historical Research". Usually null.
- date_of_original: when the original document was made, as printed on it, e.g. "1947"
  or "1947-06". Null when not printed.
- date_of_digital: when the scan was made. Almost never printed on the document: null
  unless it clearly says so.
- identifier: a catalog or collection number printed on the document, copied exactly,
  e.g. "CRBS 02-001". Null when none is printed.
- meta_subject: what the document is about, in a few words a researcher would search
  for, e.g. "Camp Russell waterfront staff, summer 1952".
- meta_location: the place the document concerns, e.g. "Woodgate, NY". Null when
  unclear.
- meta_physical_description: what the original physically is, in the archive's usual
  form "Type; size", e.g. "Newspaper; 4 x 9 in. (10 x 22 cm.)". Guess the type only
  (e.g. "Newsletter"), never the measurements, when the size is not stated.

Credits (strings or null):
- author: the named writer of the document.
- photographer: the named photographer.
- contributors: a list of other named contributors, or [].
