"""Turn parse.py output into src/data/results.json for the Results page.

Usage: python3 scripts/results/build.py <crawl dir>/results.json

Gives each row a readable championship name, a short race label and, for
endurance races, the team-mates. Rows are sorted newest season first.
"""
import json, re, sys

rows = json.load(open(sys.argv[1]))

BSB = {2021: "British Junior Supersport", 2022: "British Junior Supersport", 2023: "British Junior Supersport",
       2024: "Kawasaki British Superteens", 2025: "Pirelli National Sportbike", 2026: "Pirelli British Sportbike"}
CODES = {
    "400": "No Limits Modern 400", "CB500": "No Limits CB500", "CB5": "No Limits CB500",
    "PSS": "Pirelli Super Series 600", "NNGS": "No Limits Next-Gen Sportbike", "CUP6": "No Limits Cup 600",
    "DN5E": "Dunlop NL500", "C6": "Club 600 Endurance", "N6": "National 600 Endurance",
}
# Rows whose class code is not printed in the PDF.
HEADER_FALLBACK = [
    (r"Pirelli Super Series 600", "Pirelli Super Series 600"),
    (r"Next-Gen", "No Limits Next-Gen Sportbike"),
    (r"Modern & Retro 400", "No Limits Modern 400"),
]
VENUES = {(2025, "Round 2"): "Donington Park"}  # missing from the TSL event title


def championship(r):
    if r["series"] == "BSB":
        return BSB[r["year"]]
    if r.get("code") in CODES:
        return CODES[r["code"]]
    for pat, name in HEADER_FALLBACK:
        if re.search(pat, r["class"]):
            return name
    return r["class"]


def race_label(session):
    s = session.replace(" Result", "").strip()
    return "Race" if s == "Race" else s


out = []
for r in rows:
    champ = championship(r)
    endurance = "Endurance" in champ
    riders = None
    if endurance and r.get("riders"):
        riders = [p.strip() for p in r["riders"].split("/") if "SMART" not in p]
        riders = [re.sub(r"\b([A-Z][A-Z'-]+)\b", lambda m: m.group(1).title(), p) for p in riders]
    out.append({
        "year": r["year"],
        "series": "BSB" if r["series"] == "BSB" else "No Limits Racing",
        "championship": champ,
        "endurance": endurance,
        "round": r["round"],
        "venue": r["venue"] or VENUES.get((r["year"], r["round"]), ""),
        "date": r["date"],
        "race": "Endurance race" if endurance else race_label(r["session"]),
        "pos": r["pos"],
        "classPos": r.get("classPos"),
        "number": r["no"],
        "bestLap": r.get("best"),
        "teamMates": riders,
        "source": r["url"],
    })

out.sort(key=lambda r: (-r["year"], r["series"] != "BSB", r["championship"], r["source"]))
json.dump(out, open("src/data/results.json", "w"), indent=1, ensure_ascii=False)
print(len(out), "rows -> src/data/results.json")
