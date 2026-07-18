# Deskly Knowledge Base

**Wissensdatenbank & Help Center für Shopware 6** – SEO-optimierte Hilfe-Seiten unter `/hilfe`, FAQ-CMS-Element und eine öffentliche API. Seit **2026-07-18** im Produktivbetrieb auf [hinzke.de/hilfe](https://www.hinzke.de/hilfe).

> Entwickelt von [hinzke.digital](https://hinzke.digital) – Digitale Lösungen für den Mittelstand.

---

## Betriebsmodell: FreeScout ist die Quelle der Wahrheit

Die Inhalte werden **nicht in Shopware gepflegt**, sondern in FreeScout (Wissensdatenbank, Mailbox 4). Ein Sync spiegelt sie automatisch nach Deskly – Shopware ist ein reiner Mirror.

```
FreeScout (Redaktion)
   │  Export-Modul HinzkeKbExport
   │  GET inbox.hinzke.de/api/hinzke-kb-export/4?token=…
   ▼
Deskly-Sync (Scheduled Task, alle 30 Min)
   ▼
hinzke.de/hilfe  →  Kategorien, Artikel, FAQ-Blöcke
```

**Wichtig:** Artikel und Kategorien in Deskly nie manuell anlegen – der nächste Sync überschreibt sie. Redaktion passiert ausschließlich in FreeScout.

Das zugehörige FreeScout-Modul liegt im Repo [freescout-hinzke-kb-export](https://github.com/MagnusHL/freescout-hinzke-kb-export).

### Sync-Verhalten

- **Match** über `freescout_id` (Spalte in beiden Tabellen), Fallback-Adoption über Name (Kategorien) bzw. Slug (Artikel).
- **Bestehende Slugs werden nie geändert** (SEO-Stabilität). Umbenennen einer Kategorie in FreeScout erzeugt daher eine neue Deskly-Kategorie; die alte bleibt als leere Karteileiche und muss manuell gelöscht werden.
- **HTML-Sanitizer**: entfernt Inline-Styles, `class`-Müll und gefährliche URL-Schemata (`javascript:`, `data:`) – nur eine Whitelist an Tags/Attributen überlebt.
- **Link-Rewrite**: interne FreeScout-KB-Links werden auf `/hilfe/…`-URLs umgeschrieben.
- **`tags` = Kategorie-Slugs**: der Sync schreibt in jedes Artikel-`tags`-Feld die Slugs seiner Kategorien. Die FAQ-Blöcke filtern danach (siehe `docs/faq-block-mapping.md`).
- **Safety-Fuse**: bricht ab, wenn mehr als 30 % der aktiven Artikel deaktiviert würden (z. B. weil FreeScout-Artikel auf Entwurf stehen). Mit `--force` übergehbar.

### Sync auslösen

```bash
# Manuell (ignoriert syncEnabled):
bin/console deskly:kb:sync            # echter Lauf
bin/console deskly:kb:sync --dry-run  # nur Report, schreibt nichts
bin/console deskly:kb:sync --force    # Safety-Fuse übergehen

# Automatisch: Scheduled Task deskly_kb.freescout_sync (1800 s),
# läuft nur bei Plugin-Config syncEnabled = true.
```

### Plugin-Konfiguration (Einstellungen → Plugins → Deskly)

| Feld | Zweck |
|------|-------|
| `freescoutExportUrl` | Basis-URL FreeScout (Default `https://inbox.hinzke.de`) |
| `freescoutMailboxId` | Mailbox-ID (Default 4) |
| `freescoutExportToken` | Token des Export-Endpoints |
| `syncEnabled` | Scheduled Task an/aus (Default false) |

---

## FreeScout-Link-Umleitung

FreeScout ist als KB-Custom-Domain `hinzke.de/hilfe` konfiguriert. KB-Links in Mails werden dadurch `hinzke.de/hilfe/hc/…`. Ein Redirect-Controller fängt sie ab:

| Eingehend | Ziel (301) |
|-----------|-----------|
| `/hilfe/hc/{mailbox}/{artikel}/{slug?}` | `/hilfe/{kategorie}/{artikel}` |
| `/hc/{mailbox}/{artikel}/{slug?}` (Altlinks) | `/hilfe/{kategorie}/{artikel}` |
| `/hilfe/hc/{mailbox}` bzw. `/hc/{mailbox}` | `/hilfe` (Übersicht) |

---

## Features

- **Storefront** unter `/hilfe`: Übersicht, Kategorie- und Artikelseiten, Suche, Breadcrumb.
- **SEO**: automatische SEO-URLs, Sitemap-Integration, JSON-LD (Article, FAQPage, BreadcrumbList, CollectionPage), Meta-Felder je Kategorie/Artikel.
- **FAQ-Block** für den Erlebniswelt-Editor: filtert Artikel nach Tag (= Kategorie-Slug), HTML5-Accordion, eingebettetes FAQPage-Schema.

### Öffentliche API (`/api/deskly-kb/public/`)

Nur aktive Inhalte, kompaktes Format (u. a. für Chatbots):

| Methode | Endpoint | Beschreibung |
|---------|----------|--------------|
| `GET` | `/categories` | Aktive Kategorien |
| `GET` | `/articles` | Aktive Artikel (Filter: `q`, `categorySlug`, `tag`) |
| `GET` | `/articles/{slug}` | Artikel nach Slug |
| `GET` | `/search?q=…` | Kompakte Suche |

Eine Admin-API (`/api/deskly-kb/…`, mit Auth) erlaubt vollen CRUD auf Kategorien und Artikel – wird vom Seed/Sync und für Aufräumarbeiten genutzt, nicht für die reguläre Pflege.

---

## Datenmodell

```
deskly_kb_category
├── id, name, slug, freescout_id
├── description, meta_title, meta_description
├── position, active
└── articles (1:n)

deskly_kb_article
├── id, category_id (FK), freescout_id
├── title, slug
├── short_text, content
├── meta_title, meta_description
├── tags (JSON = Kategorie-Slugs), position, active
└── category (n:1)
```

---

## Installation / Deployment (AllInkl)

Server-Details und Fallstricke: siehe `.claude/instructions.md`. Kurzform:

```bash
# Vom Repo aus deployen (ersetzt src/ + composer.json auf dem Server):
git archive --format=tar HEAD -- src/ composer.json \
  | ssh allinkl "rm -rf <plugin>/src && tar xf - -C <plugin>/"

ssh allinkl "php84 <shopware>/bin/console plugin:update DesklyKnowledgeBase"
ssh allinkl "php84 <shopware>/bin/console cache:clear"
```

PHP auf dem Server ist `php84` (Standard-`php` ist 7.4). Admin-JS nur neu bauen, wenn `Resources/app/administration/` geändert wurde.

---

## Anforderungen

| Komponente | Version |
|------------|---------|
| Shopware Core / Storefront | >= 6.7.0 |
| PHP | >= 8.1 |

---

## Dokumentation im Repo

- `docs/faq-block-mapping.md` – Zuordnung FAQ-Block → Kategorie-Slug (angewendet 2026-07-18)
- `scripts/apply-faq-blocks.py` – setzt die FAQ-Block-Filter per Admin-API
- `.claude/instructions.md` – Deploy- und Server-Umgebung

---

## Lizenz

MIT

---

<p align="center">
  <a href="https://hinzke.digital"><strong>hinzke.digital</strong></a><br>
  Digitale Lösungen für den Mittelstand
</p>
