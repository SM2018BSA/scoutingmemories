# Date rules

- start_date and end_date are 4-digit years between 1900 and 2030, as strings.
- They describe the CONTENT's time span, not the scan and not the collection.
- Use the year printed on the document (masthead, dateline, cover, volume line). A
  volume/issue line like "Vol. 12, No. 3" plus a known founding year is NOT enough to
  compute a year: leave it null and mention the volume line in notes_for_reviewer.
- A season ("Summer 1947", "1947 camping season") is that year: "1947"/"1947".
- A school-year or winter span ("1947-48") is "1947"/"1948".
- An anniversary or history booklet covers its whole span: "Fifty years of Scouting in
  Utica, 1917-1967" is "1917"/"1967".
- date_of_original may carry a month when printed ("June 1947" -> "1947-06"); the
  TIMELINE years never do.
- Years in OCR text are often misread (1947 vs 1917, 1952 vs 1852). Reject a year that
  contradicts the rest of the document (a council that began in 1925 did not print a
  1905 newsletter) and say so in notes_for_reviewer.
- Never use today's date for anything.
