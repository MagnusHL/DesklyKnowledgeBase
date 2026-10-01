<?php

declare(strict_types=1);

namespace Deskly\KnowledgeBase\Subscriber;

use Deskly\KnowledgeBase\Content\KbArticle\KbArticleDefinition;
use Deskly\KnowledgeBase\Content\KbCategory\KbCategoryDefinition;
use Deskly\KnowledgeBase\Framework\Cache\KbCacheTags;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Invalidiert nach jedem Schreibzugriff auf Artikel/Kategorien genau die betroffenen
 * Hilfe-Seiten im HTTP-Cache – ohne cache:clear:http und ohne den restlichen Shop.
 *
 * Bewusst mit force: Der Shop nutzt verzögerte Invalidierung (delay_enabled), dann
 * wäre eine Änderung erst nach dem nächsten Lauf von shopware.invalidate_cache sichtbar.
 */
class KbCacheInvalidationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly CacheInvalidator $cacheInvalidator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KbArticleDefinition::ENTITY_NAME . '.written' => 'onArticleWritten',
            KbArticleDefinition::ENTITY_NAME . '.deleted' => 'onArticleWritten',
            KbCategoryDefinition::ENTITY_NAME . '.written' => 'onCategoryWritten',
            KbCategoryDefinition::ENTITY_NAME . '.deleted' => 'onCategoryWritten',
        ];
    }

    public function onArticleWritten(EntityWrittenEvent $event): void
    {
        $this->invalidate(array_map(KbCacheTags::article(...), $this->ids($event)));
    }

    public function onCategoryWritten(EntityWrittenEvent $event): void
    {
        $this->invalidate(array_map(KbCacheTags::category(...), $this->ids($event)));
    }

    /**
     * @param list<string> $tags
     */
    private function invalidate(array $tags): void
    {
        if ($tags === []) {
            return;
        }

        // Listen (Übersicht, Kategorien, FAQ-Blöcke) hängen an Titel, Position und Aktiv-Status jedes Artikels
        $tags[] = KbCacheTags::LISTING;

        $this->cacheInvalidator->invalidate($tags, true);
    }

    /**
     * @return list<string>
     */
    private function ids(EntityWrittenEvent $event): array
    {
        return array_values(array_filter($event->getIds(), 'is_string'));
    }
}
