"""Crawl TSL Timing result PDFs for given clubs/years and keep the ones mentioning Lewis Smart.

Usage: python3 scripts/results/crawl.py <out dir> bsb,nolimits 2021,2022,2023,2024,2025,2026
Downloads every "Result" PDF of every event (cached in <out dir>/pdf) and writes <out dir>/hits.json.
Needs pdftotext (poppler-utils). Run parse.py and build.py afterwards.
"""
import json, os, re, subprocess, sys, time, html
from concurrent.futures import ThreadPoolExecutor
from urllib.request import Request, urlopen

BASE = "https://www.tsl-timing.com"
OUT = sys.argv[1]
CLUBS = sys.argv[2].split(",")
YEARS = [int(y) for y in sys.argv[3].split(",")]
os.makedirs(f"{OUT}/pdf", exist_ok=True)
os.makedirs(f"{OUT}/pages", exist_ok=True)


def get(url, binary=False, tries=3):
    for i in range(tries):
        try:
            with urlopen(Request(url, headers={"User-Agent": "Mozilla/5.0 (results research)"}), timeout=60) as r:
                b = r.read()
                return b if binary else b.decode("utf-8", "replace")
        except Exception as e:
            err = e
            time.sleep(2 * (i + 1))
    print("FAIL", url, err, flush=True)
    return None


def text(s):
    return html.unescape(re.sub(r"<[^>]+>", " ", s)).split() and " ".join(html.unescape(re.sub(r"<[^>]+>", " ", s)).split())


def cached_page(url, name):
    p = f"{OUT}/pages/{name}"
    if os.path.exists(p):
        return open(p).read()
    h = get(url)
    if h:
        open(p, "w").write(h)
    return h or ""


# 1. events per club/year
events = []
for club in CLUBS:
    for y in YEARS:
        h = cached_page(f"{BASE}/Results/{club}/{y}", f"{club}-{y}.html")
        seen = set()
        for m in re.finditer(r'href="/event/(\d+)"', h):
            eid = m.group(1)
            if eid in seen:
                continue
            seen.add(eid)
            events.append((club, y, eid))
print("events", len(events), flush=True)

# 2. result links per event, with class heading
jobs = []
seen_urls = set()
meta = {}
for club, y, eid in events:
    h = cached_page(f"{BASE}/event/{eid}", f"event-{eid}.html")
    title = text(re.search(r"<title>(.*?)</title>", h, re.S).group(1)) if "<title>" in h else eid
    meta[eid] = {"club": club, "year": y, "title": title}
    # walk tokens in order: headings and links
    heading = ""
    for m in re.finditer(r'<h[1-6][^>]*>(.*?)</h[1-6]>|<a[^>]*href="(/file/\?f=[^"]+\.pdf)"[^>]*>(.*?)</a>', h, re.S):
        if m.group(1) is not None:
            t = text(m.group(1))
            if t:
                heading = t
            continue
        label = text(m.group(3))
        if re.search(r"Result", label, re.I) and m.group(2) not in seen_urls:
            seen_urls.add(m.group(2))
            jobs.append({"event": eid, "class": heading, "label": label, "url": m.group(2)})
print("result pdfs", len(jobs), flush=True)


def work(j):
    fn = re.sub(r"[^A-Za-z0-9._-]", "_", j["url"].split("f=")[1])
    p = f"{OUT}/pdf/{fn}"
    if not os.path.exists(p):
        b = get(BASE + j["url"], binary=True)
        if not b:
            return j, None
        open(p, "wb").write(b)
    t = subprocess.run(["pdftotext", "-layout", p, "-"], capture_output=True, text=True).stdout
    return j, t


hits = []
done = 0
with ThreadPoolExecutor(4) as ex:
    for j, t in ex.map(work, jobs):
        done += 1
        if done % 100 == 0:
            print("done", done, flush=True)
        if not t:
            continue
        lines = t.splitlines()
        rows = [l for l in lines if re.search(r"\bSMART\b", l, re.I) and re.search(r"\bLEWIS\b|\bL\.? ?SMART\b|SMART,? L", l, re.I)]
        if rows:
            head = [l.strip() for l in lines[:12] if l.strip()]
            hits.append({**j, **meta[j["event"]], "rows": [r.strip() for r in rows], "header": head})
json.dump(hits, open(f"{OUT}/hits.json", "w"), indent=1)
print("hits", len(hits), flush=True)
