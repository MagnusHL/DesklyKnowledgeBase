<?php

declare(strict_types=1);

namespace Deskly\KnowledgeBase\Util;

/**
 * Baut Seitentitel und Meta-Descriptions – gemeinsam genutzt von Sync (Vorbelegung
 * der Meta-Felder) und Storefront (Fallback, wenn die Felder leer sind).
 */
final class MetaText
{
    public const TITLE_SUFFIX = ' | Druckerei Hinzke Lübeck';
    public const TITLE_MAX_LENGTH = 60;
    public const DESCRIPTION_MAX_LENGTH = 155;

    /**
     * Titel mit Marken-Suffix, solange er in 60 Zeichen passt – sonst ohne Suffix.
     * Lange Titel bleiben vollständig: ein mitten in der Frage abgeschnittener Titel
     * ("… auf dem Auftragszettel") ist schlechter als einer, den Google selbst kürzt.
     */
    public static function title(string $title): string
    {
        $full = $title . self::TITLE_SUFFIX;

        if (mb_strlen($full, 'UTF-8') <= self::TITLE_MAX_LENGTH) {
            return $full;
        }

        return $title;
    }

    /**
     * HTML oder Fließtext → einzeilige, gekürzte Meta-Description ('' wenn leer).
     */
    public static function description(string $text): string
    {
        $text = html_entity_decode(strip_tags($text), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $flat = trim((string) preg_replace('/\s+/u', ' ', $text));

        if ($flat === '') {
            return '';
        }

        return self::truncate($flat, self::DESCRIPTION_MAX_LENGTH);
    }

    public static function truncate(string $text, int $maxLength): string
    {
        if (mb_strlen($text, 'UTF-8') <= $maxLength) {
            return $text;
        }

        $cut = mb_substr($text, 0, $maxLength, 'UTF-8');
        $lastSpace = mb_strrpos($cut, ' ', 0, 'UTF-8');

        if ($lastSpace !== false && $lastSpace > $maxLength * 0.5) {
            $cut = mb_substr($cut, 0, $lastSpace, 'UTF-8');
        }

        return rtrim($cut, " \t-,.;:");
    }
}
