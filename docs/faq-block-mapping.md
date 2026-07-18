# FAQ-Block Umstellung: alter Tag-Filter → Kategorie-Slug

Stand: 2026-07-18 · **STATUS: ANGEWENDET am 2026-07-18** (30 Blöcke gesetzt, 11 waren schon korrekt) (der Sync schreibt die
Kategorie-Slugs als `tags`; vorher würden die Blöcke leer laufen).

Jeder `deskly-faq-block` filtert per `tags` (kommagetrennt, OR-Match via `array_intersect`).
Nach dem Sync trägt jeder Artikel die Slugs seiner FreeScout-Kategorien als Tags. Also: jeden
Block-Filter auf den Ziel-Slug unten setzen.

Entscheidungen Magnus (2026-07-18): gebundene Produkte → `bindungen`, übrige Produkt-Kategorien
→ `produkte`; Landingpage Versand & Zahlung → kombiniert; Polymere Folie → `werbetechnik`.

## Vollständiges Mapping (42 Blöcke)

| # | Seite / Produkt | neuer Tag-Filter | Status |
|---|---|---|---|
| 1 | Dental Labore (Kat) | `dental-labore` | klar |
| 2 | SHK Betriebe (Kat) | `shk-betriebe` | klar |
| 3 | Branchen-Hub (Kat) | `branchen` | klar |
| 4 | Plot (Kat) | `plot` | klar |
| 5 | Poster/Plakate (Kat) | `poster-plakate` | klar |
| 6 | Drucken & Binden (Kat) | `drucken-binden` | klar |
| 7 | Großformatdruck (Kat) | `grossformatdruck-faq` | klar (echter Deskly-Slug, Sync bewahrte Bestand) |
| 8 | Sleeking/Veredelung (Kat) | `material-veredelung` | klar |
| 9 | Werbetechnik (Kat) | `werbetechnik` | klar |
| 10 | Home (Startseite) | `haeufige-fragen` | klar |
| 11 | Über uns (Landingpage) | `ueber-uns` | klar |
| 12 | 3D-Druck Service (Produkt) | `3d-druck` | klar |
| 13 | Poster / Plakate (Produkt) | `poster-plakate` | klar |
| 14 | Gestaltungs-Pauschale (Produkt) | `gestaltung` | klar |
| 15 | Fälzelbindung (Produkt) | `bindungen` | klar |
| 16 | Hardcover-Bindung (Produkt) | `bindungen` | klar |
| 17 | Rückenstichheftung (Produkt) | `bindungen` | klar |
| 18 | Softcover-Bindung (Produkt) | `bindungen` | klar |
| 19 | Spiralbindung (Produkt) | `bindungen` | klar |
| 20 | Bindungen (Kat) | `bindungen` | klar |
| 21 | Über uns (Kat) | `ueber-uns` | klar |
| 22 | Textildruck (Kat) | `textildruck` | dünn (2 Artikel) – ggf. Block weglassen |
| 23 | Plot (Kat, inaktiv) | `plot` | erst bei Reaktivierung |
| 24 | Flyer (Kat) | `flyer` | klar |
| 25 | Falzflyer DIN lang (Produkt) | `flyer` | klar |
| 26 | Flyer DIN A4 (Produkt) | `flyer` | klar |
| 27 | Versand & Logistik (Kat) | `bestellung-versand` | klar |
| 28 | Aufkleber (Kat) | `werbetechnik` | klar |
| 29 | Banner (Kat) | `grossformatdruck-faq` | klar |
| 30 | Rollups (Kat) | `grossformatdruck-faq` | klar |
| 31 | Schilder (Kat) | `werbetechnik` | klar |
| 32 | Versand und Zahlung (Landingpage) | `bestellung-versand,zahlung-rechnung` | Entscheidung: kombiniert |
| 33 | Polymere Folie (Produkt) | `werbetechnik` | Entscheidung |
| 34 | Abizeitung (Kat) | `bindungen` | Entscheidung: gebunden |
| 35 | Blöcke (Kat) | `produkte` | Entscheidung |
| 36 | Broschüren (Kat) | `bindungen` | Entscheidung: gebunden |
| 37 | Durchschreibesätze (Kat) | `produkte` | Entscheidung |
| 38 | Hochzeitszeitungen (Kat) | `bindungen` | Entscheidung: gebunden |
| 39 | Vereinszeitschriften (Kat) | `bindungen` | Entscheidung: gebunden |
| 40 | Visitenkarten (Kat) | `produkte` | Entscheidung |
| 41 | Webseiten (Kat) | `produkte` | Entscheidung |
| 42 | Magnus Test (Kat, inaktiv) | – | ignorieren / aufräumen |

## Hinweise für die Anwendung

- **maxItems = 15** überall. Kategorien > 15 Artikel (Bindungen, Bestellung & Versand,
  Häufige Fragen, Poster & Plakate) zeigen nur die ersten 15 – ggf. erhöhen.
- Tag-Filter liegen im `slotConfig`-Override der nutzenden Seite (Kategorie/Produkt/Landingpage),
  nicht im Default-Slot. Beim Anwenden also die Seiten-Overrides anfassen.
- Textildruck (22) und die 2 FAQ-Slots auf ungenutzten Layouts sind Aufräum-Kandidaten.
