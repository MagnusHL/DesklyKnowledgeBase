<?php

declare(strict_types=1);

namespace Deskly\KnowledgeBase\Framework\Cache;

/**
 * HTTP-Cache-Tags der Hilfe-Seiten. Die Seiten setzen sie beim Rendern,
 * KbCacheInvalidationSubscriber invalidiert sie bei jedem Schreibzugriff (Sync, Admin-API).
 */
final class KbCacheTags
{
    /** Übersicht, Kategorie-Seiten und CMS-Seiten mit FAQ-Block – alles, was Artikel auflistet */
    public const LISTING = 'deskly-kb-listing';

    public static function article(string $id): string
    {
        return 'deskly-kb-article-' . $id;
    }

    public static function category(string $id): string
    {
        return 'deskly-kb-category-' . $id;
    }
}
