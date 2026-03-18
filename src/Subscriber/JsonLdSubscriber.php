<?php

declare(strict_types=1);

namespace Deskly\KnowledgeBase\Subscriber;

use Deskly\KnowledgeBase\Content\KbArticle\KbArticleEntity;
use Deskly\KnowledgeBase\Content\KbCategory\KbCategoryEntity;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class JsonLdSubscriber implements EventSubscriberInterface
{
    private const KB_ROUTES = [
        'frontend.kb.article',
        'frontend.kb.category',
        'frontend.kb.overview',
    ];

    public static function getSubscribedEvents(): array
    {
        return [
            StorefrontRenderEvent::class => 'onStorefrontRender',
        ];
    }

    public function onStorefrontRender(StorefrontRenderEvent $event): void
    {
        $route = $event->getRequest()->attributes->get('_route', '');

        if (!\in_array($route, self::KB_ROUTES, true)) {
            return;
        }

        $parameters = $event->getParameters();
        $page = $parameters['page'] ?? null;

        if ($page === null) {
            return;
        }

        $shopName = $event->getSalesChannelContext()->getSalesChannel()->getName() ?? 'Hinzke';
        $baseUrl = $event->getRequest()->getSchemeAndHttpHost();

        $jsonLdItems = [];

        match ($route) {
            'frontend.kb.article' => $this->buildArticleJsonLd($jsonLdItems, $page, $shopName, $baseUrl),
            'frontend.kb.category' => $this->buildCategoryJsonLd($jsonLdItems, $page, $baseUrl),
            'frontend.kb.overview' => $this->buildOverviewJsonLd($jsonLdItems, $baseUrl),
        };

        if ($jsonLdItems !== []) {
            $event->setParameter('desklyJsonLd', $jsonLdItems);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $jsonLdItems
     */
    private function buildArticleJsonLd(array &$jsonLdItems, object $page, string $shopName, string $baseUrl): void
    {
        $article = method_exists($page, 'getArticle') ? $page->getArticle() : null;

        if (!$article instanceof KbArticleEntity) {
            return;
        }

        $category = method_exists($page, 'getCategory') ? $page->getCategory() : $article->getCategory();

        // Article-Schema
        $articleSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $article->getMetaTitle() ?? $article->getTitle(),
            'description' => $article->getMetaDescription() ?? $article->getShortText(),
            'datePublished' => $article->getCreatedAt()?->format('c') ?? '',
            'dateModified' => ($article->getUpdatedAt() ?? $article->getCreatedAt())?->format('c') ?? '',
            'publisher' => [
                '@type' => 'Organization',
                'name' => $shopName,
            ],
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => $baseUrl . '/hilfe/' . ($category?->getSlug() ?? '') . '/' . $article->getSlug(),
            ],
        ];

        $jsonLdItems[] = $articleSchema;

        // BreadcrumbList-Schema
        $breadcrumbItems = [
            [
                '@type' => 'ListItem',
                'position' => 1,
                'name' => 'Hilfe',
                'item' => $baseUrl . '/hilfe',
            ],
        ];

        if ($category instanceof KbCategoryEntity) {
            $breadcrumbItems[] = [
                '@type' => 'ListItem',
                'position' => 2,
                'name' => $category->getName(),
                'item' => $baseUrl . '/hilfe/' . $category->getSlug(),
            ];
        }

        $breadcrumbItems[] = [
            '@type' => 'ListItem',
            'position' => \count($breadcrumbItems) + 1,
            'name' => $article->getTitle(),
        ];

        $jsonLdItems[] = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $breadcrumbItems,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $jsonLdItems
     */
    private function buildCategoryJsonLd(array &$jsonLdItems, object $page, string $baseUrl): void
    {
        $category = method_exists($page, 'getCategory') ? $page->getCategory() : null;

        if (!$category instanceof KbCategoryEntity) {
            return;
        }

        // CollectionPage-Schema
        $jsonLdItems[] = [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => $category->getMetaTitle() ?? $category->getName(),
            'description' => $category->getMetaDescription() ?? $category->getDescription() ?? '',
            'url' => $baseUrl . '/hilfe/' . $category->getSlug(),
        ];

        // BreadcrumbList
        $jsonLdItems[] = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => 'Hilfe',
                    'item' => $baseUrl . '/hilfe',
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => $category->getName(),
                ],
            ],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $jsonLdItems
     */
    private function buildOverviewJsonLd(array &$jsonLdItems, string $baseUrl): void
    {
        $jsonLdItems[] = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => 'Hilfe & Support',
            'description' => 'Hilfe-Center mit Anleitungen, FAQs und Support-Artikeln.',
            'url' => $baseUrl . '/hilfe',
        ];

        // BreadcrumbList (nur Startseite -> Hilfe)
        $jsonLdItems[] = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => 'Hilfe',
                ],
            ],
        ];
    }
}
