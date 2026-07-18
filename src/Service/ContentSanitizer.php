<?php

declare(strict_types=1);

namespace Deskly\KnowledgeBase\Service;

/**
 * Bereinigt HTML aus dem FreeScout-Export und schreibt interne KB-Links um.
 *
 * Bewusst ohne externe Abhängigkeiten – nutzt nur ext-dom (Shopware-Voraussetzung).
 */
class ContentSanitizer
{
    /**
     * Erlaubte Tags mit den jeweils erlaubten Attributen.
     * Alle anderen Tags werden aufgelöst, ihr Inhalt bleibt erhalten.
     */
    private const ALLOWED_TAGS = [
        'p' => [],
        'br' => [],
        'b' => [],
        'strong' => [],
        'i' => [],
        'em' => [],
        'u' => [],
        'h2' => [],
        'h3' => [],
        'h4' => [],
        'ul' => [],
        'ol' => [],
        'li' => [],
        'a' => ['href', 'target'],
        'img' => ['src', 'alt'],
        'table' => [],
        'thead' => [],
        'tbody' => [],
        'tr' => [],
        'th' => [],
        'td' => [],
        'hr' => [],
        'blockquote' => [],
    ];

    /** Hosts, deren Knowledgebase-Links auf Deskly-URLs umgeschrieben werden. */
    private const KB_LINK_HOSTS = ['inbox.hinzke.de', 'hilfe.hinzke.de'];

    /** Erlaubte URL-Schemata in href/src (verhindert javascript:/data:/vbscript:-XSS). */
    private const ALLOWED_URL_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /** Attribute, die eine URL enthalten und daher schema-geprüft werden. */
    private const URL_ATTRIBUTES = ['href', 'src'];

    /**
     * Entfernt class/style-Attribute, nicht erlaubte Tags (Inhalt bleibt),
     * leere Absätze, Kommentare und \r.
     */
    public function sanitize(string $html): string
    {
        $html = str_replace("\r", '', $html);

        if (trim($html) === '') {
            return '';
        }

        $document = $this->createDocument($html);
        $root = $document->documentElement;

        if ($root === null) {
            return '';
        }

        foreach (iterator_to_array($root->childNodes) as $child) {
            $this->sanitizeNode($child);
        }

        $this->removeEmptyParagraphs($root);

        return $this->renderChildren($root);
    }

    /**
     * Schreibt FreeScout-KB-Links auf Deskly-URLs um.
     *
     * @param callable(int): ?string $resolver liefert zur FreeScout-Artikel-ID die relative Deskly-URL oder null
     *
     * @return array{html: string, warnings: list<string>}
     */
    public function rewriteLinks(string $html, callable $resolver): array
    {
        if (trim($html) === '') {
            return ['html' => $html, 'warnings' => []];
        }

        $document = $this->createDocument($html);
        $root = $document->documentElement;

        if ($root === null) {
            return ['html' => $html, 'warnings' => []];
        }

        $warnings = [];

        foreach (iterator_to_array($document->getElementsByTagName('a')) as $anchor) {
            $href = $anchor->getAttribute('href');
            $articleId = $this->extractFreeScoutArticleId($href);

            if ($articleId === null) {
                continue;
            }

            $target = $resolver($articleId);

            if ($target !== null) {
                $anchor->setAttribute('href', $target);
                continue;
            }

            $warnings[] = sprintf(
                'Link auf FreeScout-Artikel %d nicht auflösbar, Link entfernt (Linktext bleibt): %s',
                $articleId,
                $href
            );
            $this->unwrap($anchor);
        }

        return ['html' => $this->renderChildren($root), 'warnings' => $warnings];
    }

    /**
     * Wandelt HTML in Klartext um; Absätze bleiben als Doppel-Zeilenumbruch erhalten.
     */
    public function toPlainText(string $html): string
    {
        $html = (string) preg_replace('#</(p|h2|h3|h4|li|blockquote|tr|div)>#i', "\n\n", $html);
        $html = (string) preg_replace('#<br\s*/?>#i', "\n", $html);

        $text = html_entity_decode(strip_tags($html), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $text = str_replace(["\r", "\u{00A0}"], ['', ' '], $text);
        $text = (string) preg_replace('/[ \t]+/', ' ', $text);
        $text = (string) preg_replace('/ ?\n ?/', "\n", $text);
        $text = (string) preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    private function sanitizeNode(\DOMNode $node): void
    {
        if ($node instanceof \DOMComment || $node instanceof \DOMCdataSection) {
            $node->parentNode?->removeChild($node);

            return;
        }

        if (!$node instanceof \DOMElement) {
            return;
        }

        // Kinder zuerst (Snapshot, weil sich die Liste beim Umbau ändert)
        foreach (iterator_to_array($node->childNodes) as $child) {
            $this->sanitizeNode($child);
        }

        $tag = strtolower($node->tagName);

        if (!isset(self::ALLOWED_TAGS[$tag])) {
            // Tag auflösen, Inhalt behalten (löst auch font-size-Spans auf)
            $this->unwrap($node);

            return;
        }

        // Attribute auf die Whitelist reduzieren (entfernt class, style, ...)
        foreach (iterator_to_array($node->attributes) as $attribute) {
            if (!\in_array($attribute->name, self::ALLOWED_TAGS[$tag], true)) {
                $node->removeAttribute($attribute->name);

                continue;
            }

            // URL-Attribute gegen gefährliche Schemata absichern (javascript:, data:, ...)
            if (\in_array($attribute->name, self::URL_ATTRIBUTES, true) && !$this->isSafeUrl($attribute->value)) {
                $node->removeAttribute($attribute->name);
            }
        }

        // a-Tag ohne (sicheres) href auflösen – Linktext bleibt erhalten
        if ($tag === 'a' && !$node->hasAttribute('href')) {
            $this->unwrap($node);

            return;
        }

        // img ohne (sicheres) src entfernen
        if ($tag === 'img' && !$node->hasAttribute('src')) {
            $node->parentNode?->removeChild($node);
        }
    }

    /**
     * Lässt nur unbedenkliche URL-Schemata zu. Relative URLs, Anker (#…)
     * und protokoll-relative URLs (//host) gelten als sicher.
     */
    private function isSafeUrl(string $url): bool
    {
        $trimmed = ltrim($url);

        if ($trimmed === '') {
            return false;
        }

        if (str_starts_with($trimmed, '#') || str_starts_with($trimmed, '/')) {
            return true;
        }

        // Kein Schema-Trenner vor dem ersten / , ? oder # → relative URL
        if (preg_match('#^[a-zA-Z][a-zA-Z0-9+.\-]*:#', $trimmed, $m) !== 1) {
            return true;
        }

        $scheme = strtolower(rtrim($m[0], ':'));

        return \in_array($scheme, self::ALLOWED_URL_SCHEMES, true);
    }

    private function removeEmptyParagraphs(\DOMElement $root): void
    {
        foreach (iterator_to_array($root->getElementsByTagName('p')) as $paragraph) {
            if ($paragraph->getElementsByTagName('img')->length > 0) {
                continue;
            }

            $text = str_replace("\u{00A0}", ' ', $paragraph->textContent);

            if (trim($text) === '') {
                $paragraph->parentNode?->removeChild($paragraph);
            }
        }
    }

    /**
     * Erkennt FreeScout-KB-Links und extrahiert die Artikel-ID.
     * Unterstützte Muster: /kb/article/{id}, /hc/{mailboxId}/{articleId}-slug, /hc/{mailboxId}/{articleId}/...
     */
    private function extractFreeScoutArticleId(string $href): ?int
    {
        $parts = parse_url(trim($href));

        if ($parts === false || empty($parts['path'])) {
            return null;
        }

        $host = strtolower($parts['host'] ?? '');

        // Nur bekannte KB-Hosts anfassen; hostlose (relative) Links nur bei eindeutigen KB-Pfaden
        if ($host !== '' && !\in_array($host, self::KB_LINK_HOSTS, true)) {
            return null;
        }

        $path = $parts['path'];

        if (preg_match('#^/kb/article/(\d+)#', $path, $matches) === 1) {
            return (int) $matches[1];
        }

        if (preg_match('#^/hc/\d+/(\d+)(?:[-/]|$)#', $path, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    private function createDocument(string $html): \DOMDocument
    {
        $document = new \DOMDocument('1.0', 'UTF-8');

        $previousErrorSetting = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div>' . $html . '</div>',
            \LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrorSetting);

        return $document;
    }

    private function renderChildren(\DOMElement $root): string
    {
        $document = $root->ownerDocument;

        if ($document === null) {
            return '';
        }

        $html = '';
        foreach ($root->childNodes as $child) {
            $html .= $document->saveHTML($child);
        }

        return trim($html);
    }

    private function unwrap(\DOMElement $element): void
    {
        $parent = $element->parentNode;

        if ($parent === null) {
            return;
        }

        while ($element->firstChild !== null) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }
}
