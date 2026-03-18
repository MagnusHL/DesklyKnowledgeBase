<?php

declare(strict_types=1);

namespace Deskly\KnowledgeBase\Core\Content\Sitemap\Provider;

use Deskly\KnowledgeBase\Content\KbArticle\KbArticleEntity;
use Shopware\Core\Content\Sitemap\Provider\AbstractUrlProvider;
use Shopware\Core\Content\Sitemap\Struct\Url;
use Shopware\Core\Content\Sitemap\Struct\UrlResult;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class KbArticleUrlProvider extends AbstractUrlProvider
{
    private const BATCH_SIZE = 100;

    public function __construct(
        private readonly EntityRepository $articleRepository,
    ) {
    }

    public function getName(): string
    {
        return 'deskly_kb_article';
    }

    /**
     * {@inheritdoc}
     */
    public function getUrls(SalesChannelContext $context, int $limit, ?int $offset = null): UrlResult
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addFilter(new EqualsFilter('category.active', true));
        $criteria->addAssociation('category');
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING));
        $criteria->setLimit($limit);

        if ($offset !== null) {
            $criteria->setOffset($offset);
        }

        $articles = $this->articleRepository->search($criteria, $context->getContext());

        if ($articles->getTotal() === 0) {
            return new UrlResult([], null);
        }

        $urls = [];

        /** @var KbArticleEntity $article */
        foreach ($articles->getEntities() as $article) {
            $category = $article->getCategory();

            if ($category === null) {
                continue;
            }

            $lastMod = $article->getUpdatedAt() ?? $article->getCreatedAt();

            $url = new Url();
            $url->setLoc('hilfe/' . $category->getSlug() . '/' . $article->getSlug());
            $url->setLastmod($lastMod);
            $url->setChangefreq('weekly');
            $url->setResource(KbArticleEntity::class);
            $url->setIdentifier($article->getId());

            $urls[] = $url;
        }

        $nextOffset = ($offset ?? 0) + $limit;

        if (\count($articles->getEntities()) < $limit) {
            $nextOffset = null;
        }

        return new UrlResult($urls, $nextOffset);
    }

    public function getDecorated(): AbstractUrlProvider
    {
        throw new \RuntimeException('KbArticleUrlProvider hat keinen dekorierten Service.');
    }
}
