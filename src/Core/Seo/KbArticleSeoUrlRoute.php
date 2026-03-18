<?php

declare(strict_types=1);

namespace Deskly\KnowledgeBase\Core\Seo;

use Deskly\KnowledgeBase\Content\KbArticle\KbArticleDefinition;
use Deskly\KnowledgeBase\Content\KbArticle\KbArticleEntity;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlMapping;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class KbArticleSeoUrlRoute implements SeoUrlRouteInterface
{
    public const ROUTE_NAME = 'frontend.kb.article';
    public const DEFAULT_TEMPLATE = 'hilfe/{{ article.category.slug }}/{{ article.slug }}';

    public function __construct(
        private readonly KbArticleDefinition $articleDefinition,
    ) {
    }

    public function getConfig(): SeoUrlRouteConfig
    {
        return new SeoUrlRouteConfig(
            $this->articleDefinition,
            self::ROUTE_NAME,
            self::DEFAULT_TEMPLATE,
        );
    }

    public function prepareCriteria(Criteria $criteria, SalesChannelEntity $salesChannel): void
    {
        $criteria->addAssociation('category');
    }

    public function getMapping(Entity $entity, ?SalesChannelEntity $salesChannel): SeoUrlMapping
    {
        /** @var KbArticleEntity $entity */
        return new SeoUrlMapping(
            $entity,
            ['categorySlug' => $entity->getCategory()?->getSlug() ?? '', 'articleSlug' => $entity->getSlug()],
            ['article' => $entity],
        );
    }
}
