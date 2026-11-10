"""Turn TSL hits (crawl.py output) into race-by-race rows for Lewis Smart.

Usage: python3 scripts/results/parse.py <crawl dir>   ->  <crawl dir>/results.json
Then:  python3 scripts/results/build.py <crawl dir>/results.json  ->  src/data/results.json
"""
import json, re, subprocess, sys, os

BASE = sys.argv[1]  # scratchpad/tsl
hits = json.load(open(f"{BASE}/hits.json"))
POS = re.compile(r"^(\d{1,3}|DNF|DNS|DSQ|NC|RET|EXC|DNQ)$")
LAP = re.compile(r"(?<![\d:.])(\d{1,2}:\d{2}\.\d{3}|\d{2}\.\d{3})(?![\d])")
MONTHS = "January February March April May June July August September October November December".split()


def pdf_text(url):
    fn = re.sub(r"[^A-Za-z0-9._-]", "_", url.split("f=")[1])
    return subprocess.run(["pdftotext", "-layout", f"{BASE}/pdf/{fn}", "-"], capture_output=True, text=True).stdout


def event_meta(title, year):
    t = title.replace("Event Details - ", "").split(" :: ")[0]
    parts = [p.strip() for p in t.split(" - ")]
    rnd = next((p for p in parts if p.startswith("Round")), "")
    date = next((p for p in parts if any(m in p for m in MONTHS)), "")
    venue = ""
    if date:
        i = parts.index(date)
        if i + 1 < len(parts) and parts[i + 1] not in ("MSVR", "No Limits Racing"):
            venue = parts[i + 1]
    date = re.sub(r"\s+\d{4}$", "", date)
    return rnd, date, venue


def class_name(lines):
    for l in lines[:8]:
        s = l.strip()
        if not s or s.startswith("MCRCB") or "CLASSIFICATION" in s or s.startswith("POS") or s.startswith("Race Distance"):
            continue
        if re.search(r"Championship - Round|Racing \d{4} - Rnd|British Superbike Championship - Round", s):
            continue
        return re.sub(r"^\d{4}\s+", "", s)
    return ""


rows, skipped = [], []
for x in hits:
    text = pdf_text(x["url"])
    lines = text.splitlines()
    idx = [i for i, l in enumerate(lines) if re.search(r"Lewis SMART\b", l)]
    found = None
    for i in idx:
        own = re.sub(r"(\d)Lewis", r"\1 Lewis", lines[i]).split()
        # look at earlier lines only when this line has no position of its own (endurance rows wrap)
        tries = (i,) if len(own) > 1 and POS.match(own[0]) and re.fullmatch(r"\d{1,3}\*?", own[1]) else (i, i - 1, i - 2, i - 3)
        for j in tries:
            if j < 0:
                continue
            tok = re.sub(r"(\d)Lewis", r"\1 Lewis", lines[j]).replace("*", "").split()
            if len(tok) >= 2 and POS.match(tok[0]) and re.fullmatch(r"\d{1,3}", tok[1]):
                found = (j, i, tok)
                break
        if found:
            break
    if not found:
        skipped.append((x["year"], x["title"][:60], x["label"], [lines[i].strip()[:80] for i in idx]))
        continue
    j, i, tok = found
    line = lines[j]
    pos, no = tok[0], tok[1]
    pic = None
    # NLR layouts: POS NO CL PIC NAME ...
    code = None
    if len(tok) > 3 and re.fullmatch(r"[A-Z0-9]{2,6}", tok[2]) and re.fullmatch(r"\d{1,3}", tok[3]) and (not tok[2].isdigit() or len(tok[2]) == 3):
        code, pic = tok[2], tok[3]
    elif len(tok) > 3 and re.fullmatch(r"[A-Z0-9]{2,6}", tok[2]) and tok[3] == "Lewis":
        code = tok[2]  # class code, no class position (e.g. DNF rows)
    elif len(tok) > 3 and re.fullmatch(r"\d{1,2}", tok[2]) and tok[3] == "Lewis":
        pic = tok[2]  # empty class column: "4 32 2 Lewis SMART"
    laps = LAP.findall(line + " " + (lines[i] if i != j else ""))
    best = None
    for l in laps:
        if ":" in l:
            m, sec = l.split(":")
            if int(m) <= 3:  # a lap time, not a race time
                best = l
        elif best is None and 40 <= float(l) < 60:  # sub-minute laps (Knockhill), printed without minutes
            best = l
    ecls = None
    if "Endurance" in class_name(lines):
        win = [lines[j]] + lines[j + 1: i + 1] + lines[max(0, j - 2): j]  # the position line first
        m = next((re.search(r"(?<![A-Za-z0-9])([CN]\d{1,2})(?![A-Za-z0-9])", l) for l in win if re.search(r"(?<![A-Za-z0-9])[CN]\d{1,2}(?![A-Za-z0-9])", l)), None)
        ecls = m.group(1) if m else None
        if pic is None:
            if i != j and own and re.fullmatch(r"\d{1,2}", own[0]):
                pic = own[0]  # "2 Jack JANES / Lewis SMART": class position leads the riders line
            elif len(tok) >= 3 and re.fullmatch(r"\d{1,2}", tok[2]):
                pic = tok[2]
            else:
                for l in lines[j + 1: i]:
                    if re.fullmatch(r"\s*\d{1,2}\s*", l):
                        pic = l.strip()
                        break
    rnd, date, venue = event_meta(x["title"], x["year"])
    teammates = None
    if "/" in lines[i] or "/" in line:
        m = re.search(r"([A-Z][a-z]+ [A-Z][A-Za-z'-]+(?: \([A-Z]+\))?(?: / [A-Z][a-z]+ [A-Z][A-Za-z'-]+(?: \([A-Z]+\))?)+)", line + " " + lines[i])
        teammates = m.group(1) if m else None
    rows.append({
        "year": x["year"], "series": "BSB" if x["club"] == "bsb" else "NLR", "event": x["event"], "round": rnd,
        "date": date, "venue": venue, "class": class_name(lines), "session": x["label"],
        "pos": pos, "classPos": pic, "code": ecls or code, "no": no, "best": best, "riders": teammates, "url": "https://www.tsl-timing.com" + x["url"],
    })

rows.sort(key=lambda r: (r["year"], r["event"], r["class"], r["session"]))
json.dump(rows, open(f"{BASE}/results.json", "w"), indent=1)
print(len(rows), "rows,", len(skipped), "skipped")
for s in skipped:
    print("SKIP", s)
