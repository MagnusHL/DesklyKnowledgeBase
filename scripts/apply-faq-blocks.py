#!/usr/bin/env python3
"""
Setzt den Tag-Filter der FAQ-Bloecke (deskly-faq-block) auf die Kategorie-Slugs.

  python3 apply-faq-blocks.py              Dry-Run (zeigt jede Aenderung, schreibt nichts)
  python3 apply-faq-blocks.py --write      PATCHt die slotConfig der Entities

Liest faq-update-payloads.json ([{entityType, entityId, slotId, newTags, ...}]).
Fuer jede Entity wird die BESTEHENDE slotConfig geladen und NUR der FAQ-Slot-tags-Wert
gesetzt (Merge, keine anderen Slots angefasst). Auth via Shopware Admin API (~/.zshrc).
"""
import json, os, ssl, sys, urllib.request, urllib.error

SP = "/private/tmp/claude-501/-Users-magnus-hinzke-Documents-git-SW6-Freescout2Shopware/4cd37a38-f30c-423c-915f-6511a99c018b/scratchpad"
PAYLOADS = f"{SP}/faq-update-payloads.json"
WRITE = "--write" in sys.argv
base = os.environ["SHOPWARE_API_URL"].rstrip("/")
ctx = ssl.create_default_context()

ENTITY_PATH = {"category": "category", "product": "product", "landing_page": "landing-page"}


def req(method, path, token=None, body=None):
    data = json.dumps(body).encode() if body is not None else None
    r = urllib.request.Request(base + path, data=data, method=method)
    r.add_header("Accept", "application/json")
    if data is not None:
        r.add_header("Content-Type", "application/json")
    if token:
        r.add_header("Authorization", "Bearer " + token)
    try:
        with urllib.request.urlopen(r, context=ctx) as resp:
            raw = resp.read().decode("utf-8", "replace")
            return resp.status, raw
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode("utf-8", "replace")


_, tok = req("POST", "/api/oauth/token", body={
    "grant_type": "client_credentials",
    "client_id": os.environ["SHOPWARE_API_CLIENT_ID"],
    "client_secret": os.environ["SHOPWARE_API_CLIENT_SECRET"]})
token = json.loads(tok)["access_token"]

payloads = json.load(open(PAYLOADS), strict=False)
print("== WRITE ==" if WRITE else "== DRY-RUN (nichts wird geschrieben) ==")
print(f"{len(payloads)} FAQ-Blöcke im Plan\n")

changed = skipped = errors = 0
for p in payloads:
    et, eid, slot = p["entityType"], p["entityId"], p["slotId"]
    new_tags = p["newTags"]
    name = p.get("entityName", eid)
    path = ENTITY_PATH.get(et)
    if not path or p.get("status") == "unklar":
        print(f"SKIP  [{et}] {name}: status={p.get('status')} / unbekannter Typ")
        skipped += 1
        continue

    st, raw = req("GET", f"/api/{path}/{eid}", token)
    if st != 200:
        print(f"ERR   [{et}] {name}: GET {st}")
        errors += 1
        continue
    ent = json.loads(raw, strict=False)["data"]
    sc = ent.get("attributes", ent).get("slotConfig") or {}

    slotcfg = sc.get(slot, {})
    old = (slotcfg.get("tags") or {}).get("value")
    if old == new_tags:
        print(f"OK    [{et}] {name}: tags schon '{new_tags}'")
        skipped += 1
        continue

    kind = "ändern" if p.get("hasExistingOverride") else "NEU anlegen"
    print(f"SET   [{et}] {name}: '{old}' -> '{new_tags}' ({kind}, slot {slot[:8]})")
    if WRITE:
        slotcfg.setdefault("tags", {"source": "static"})
        slotcfg["tags"]["value"] = new_tags
        slotcfg["tags"].setdefault("source", "static")
        # Neuer Override: maxItems mitgeben (sonst greift zwar der Slot-Default 15,
        # aber wir setzen es explizit passend zu den bestehenden Overrides)
        if not p.get("hasExistingOverride"):
            slotcfg.setdefault("maxItems", {"source": "static", "value": 15})
        sc[slot] = slotcfg
        pst, praw = req("PATCH", f"/api/{path}/{eid}", token, {"slotConfig": sc})
        if pst not in (200, 204):
            print(f"      ! PATCH fehlgeschlagen: {pst} {praw[:160]}")
            errors += 1
            continue
    changed += 1

print(f"\nGeändert: {changed} | übersprungen: {skipped} | Fehler: {errors}")
