<?php

declare(strict_types=1);

namespace Deskly\KnowledgeBase\Framework\Cms;

use Deskly\KnowledgeBase\Content\KbArticle\KbArticleCollection;
use Deskly\KnowledgeBase\Content\KbArticle\KbArticleEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

class FaqBlockCmsElementResolver extends AbstractCmsElementResolver
{
    private const ELEMENT_TYPE = 'deskly-faq-block';
    private const DEFAULT_MAX_ITEMS = 10;

    public function __construct(
        private readonly EntityRepository $articleRepository,
    ) {
    }

    public function getType(): string
    {
        return self::ELEMENT_TYPE;
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        $config = $slot->getFieldConfig();
        $maxItems = self::DEFAULT_MAX_ITEMS;

        if ($config->has('maxItems') && $config->get('maxItems')->getValue()) {
            $maxItems = (int) $config->get('maxItems')->getValue();
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addSorting(new FieldSorting('position', FieldSorting::ASCENDING));
        $criteria->setLimit($maxItems);
        $criteria->addAssociation('category');

        // Kategorie-Filter
        if ($config->has('categoryId') && $config->get('categoryId')->getValue()) {
            $criteria->addFilter(
                new EqualsFilter('categoryId', $config->get('categoryId')->getValue())
            );
        }

        $criteriaCollection = new CriteriaCollection();
        $criteriaCollection->add(
            'deskly_kb_articles_' . $slot->getUniqueIdentifier(),
            'deskly_kb_article',
            $criteria
        );

        return $criteriaCollection;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $config = $slot->getFieldConfig();
        $searchResult = $result->get('deskly_kb_articles_' . $slot->getUniqueIdentifier());

        if ($searchResult === null) {
            $slot->setData(new KbArticleCollection());

            return;
        }

        /** @var KbArticleCollection $articles */
        $articles = $searchResult->getEntities();

        // Tag-Filterung in PHP, da Shopware DAL keine native JSON-Array-Suche unterstuetzt
        if ($config->has('tags') && $config->get('tags')->getValue()) {
            $filterTags = array_map(
                'trim',
                explode(',', (string) $config->get('tags')->getValue())
            );
            $filterTags = array_filter($filterTags);

            if (\count($filterTags) > 0) {
                $filtered = $articles->filter(
                    static function (KbArticleEntity $article) use ($filterTags): bool {
                        $articleTags = $article->getTags();

                        if ($articleTags === null || \count($articleTags) === 0) {
                            return false;
                        }

                        return \count(array_intersect($articleTags, $filterTags)) > 0;
                    }
                );

                $articles = new KbArticleCollection($filtered->getElements());
            }
        }

        $slot->setData($articles);
    }
}
