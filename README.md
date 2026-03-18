# Deskly Knowledge Base

**API-first Wissensdatenbank & Help Center fuer Shopware 6** -- SEO-optimierte Hilfe-Seiten, FAQ-CMS-Element und eine oeffentliche API fuer Chatbots und Automatisierung.

> Entwickelt von [hinzke.digital](https://hinzke.digital) -- Digitale Loesungen fuer den Mittelstand.

---

## Features

### Wissensdatenbank mit Storefront-Rendering

- Kategorien und Artikel mit Slug-basierter URL-Struktur (`/hilfe/{kategorie}/{artikel}`)
- Responsive Storefront-Templates mit Breadcrumb-Navigation
- Suchfunktion auf der Uebersichtsseite
- Tag-System fuer flexible Artikel-Zuordnung
- Soft-Publishing ueber Active-Flag

### SEO-Optimierung

- Automatische SEO-URLs ueber Shopware SEO-URL-Routes
- Sitemap-Integration (aktive Artikel mit `weekly`-Frequenz)
- JSON-LD Structured Data (Article, FAQPage, BreadcrumbList, CollectionPage)
- Meta-Title und Meta-Description pro Kategorie und Artikel

### CMS-Integration

- **FAQ-Block** fuer den Erlebniswelt-Editor (Kategorie: Text)
- Konfigurierbar: Tags, Kategorie, maximale Anzahl
- HTML5 Accordion-Rendering (`<details>/<summary>`)
- Eingebettetes FAQPage-Schema fuer Google Rich Results

### Dual-API-Design

**Admin API** (`/api/deskly-kb/`) -- Vollzugriff mit Authentifizierung:

| Methode | Endpoint | Beschreibung |
|---------|----------|--------------|
| `GET` | `/categories` | Alle Kategorien mit Artikelanzahl |
| `POST` | `/categories` | Kategorie erstellen |
| `PATCH` | `/categories/{id}` | Kategorie aktualisieren |
| `DELETE` | `/categories/{id}` | Kategorie loeschen |
| `GET` | `/articles` | Artikel mit Pagination und Filtern |
| `GET` | `/articles/{id}` | Artikel-Details |
| `POST` | `/articles` | Artikel erstellen |
| `PATCH` | `/articles/{id}` | Artikel aktualisieren |
| `DELETE` | `/articles/{id}` | Artikel loeschen |

**Public API** (`/api/deskly-kb/public/`) -- Oeffentlich, nur aktive Inhalte:

| Methode | Endpoint | Beschreibung |
|---------|----------|--------------|
| `GET` | `/categories` | Aktive Kategorien |
| `GET` | `/articles` | Aktive Artikel (Filter: `q`, `categorySlug`, `tag`) |
| `GET` | `/articles/{slug}` | Artikel nach Slug |
| `GET` | `/search?q=...` | Kompakte Suche (Chatbot-optimiert) |

Die Public API liefert ein kompaktes Response-Format, optimiert fuer Chatbots und externe Systeme:

```json
{
  "id": "...",
  "title": "Wie liefere ich Druckdaten?",
  "slug": "druckdaten-liefern",
  "shortText": "...",
  "categorySlug": "druckdaten",
  "tags": ["faq", "druckdaten"]
}
```

---

## Datenmodell

```
deskly_kb_category
├── id, name, slug
├── description, meta_title, meta_description
├── position, active
└── articles (1:n)

deskly_kb_article
├── id, category_id (FK)
├── title, slug
├── short_text, content
├── meta_title, meta_description
├── tags (JSON), position, active
└── category (n:1)
```

---

## Storefront-Routen

| URL | Beschreibung |
|-----|--------------|
| `/hilfe` | Uebersicht aller Kategorien |
| `/hilfe/{kategorie}` | Artikel einer Kategorie |
| `/hilfe/{kategorie}/{artikel}` | Artikeldetail |

---

## Installation

```bash
# Plugin in custom/plugins/ ablegen
cp -r DesklyKnowledgeBase /path/to/shopware/custom/plugins/

# Plugin installieren und aktivieren
bin/console plugin:refresh
bin/console plugin:install --activate DesklyKnowledgeBase

# Admin-JS bauen
bin/console bundle:dump
bin/build-administration.sh

# Cache leeren
bin/console cache:clear
```

---

## Anforderungen

| Komponente | Version |
|------------|---------|
| Shopware Core | >= 6.7.0 |
| Shopware Storefront | >= 6.7.0 |
| PHP | >= 8.1 |

---

## Lizenz

MIT

---

<p align="center">
  <a href="https://hinzke.digital"><strong>hinzke.digital</strong></a><br>
  Digitale Loesungen fuer den Mittelstand
</p>
