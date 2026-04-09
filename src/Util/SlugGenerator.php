<?php

declare(strict_types=1);

namespace Deskly\KnowledgeBase\Util;

class SlugGenerator
{
    private const TRANSLITERATION_MAP = [
        'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
        'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'á' => 'a', 'à' => 'a', 'â' => 'a',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u',
        'í' => 'i', 'ì' => 'i', 'î' => 'i',
        'ñ' => 'n', 'ç' => 'c',
    ];

    public static function slugify(string $text, int $maxLength = 200): string
    {
        // Umlaut-Transliteration
        $text = strtr($text, self::TRANSLITERATION_MAP);

        // Alles auf Kleinbuchstaben
        $text = mb_strtolower($text, 'UTF-8');

        // Nicht-alphanumerische Zeichen durch Bindestriche ersetzen
        $text = (string) preg_replace('/[^a-z0-9]+/', '-', $text);

        // Mehrfache Bindestriche zusammenfassen
        $text = (string) preg_replace('/-{2,}/', '-', $text);

        // Bindestriche am Anfang und Ende entfernen
        $text = trim($text, '-');

        // Auf max. Länge kürzen (am Wortende, nicht mitten im Wort)
        if (\strlen($text) > $maxLength) {
            $text = substr($text, 0, $maxLength);
            $lastHyphen = strrpos($text, '-');
            if ($lastHyphen !== false && $lastHyphen > $maxLength * 0.6) {
                $text = substr($text, 0, $lastHyphen);
            }
        }

        return $text;
    }
}
