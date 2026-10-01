<?php

declare(strict_types=1);

namespace Deskly\KnowledgeBase\Storefront\Page;

use Deskly\KnowledgeBase\Content\KbArticle\KbArticleCollection;
use Deskly\KnowledgeBase\Content\KbArticle\KbArticleEntity;
use Deskly\KnowledgeBase\Content\KbCategory\KbCategoryCollection;
use Deskly\KnowledgeBase\Content\KbCategory\KbCategoryEntity;
use Deskly\KnowledgeBase\Framework\Cache\KbCacheTags;
use Deskly\KnowledgeBase\Util\MetaText;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Shopware\Storefront\Page\MetaInformation;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class KbPageLoader
{
    public function __construct(
        private readonly EntityRepository $categoryRepository,
        private readonly EntityRepository $articleRepository,
        private readonly GenericPageLoaderInterface $genericPageLoader,
        private readonly TranslatorInterface $translator,
        private readonly CacheTagCollector $cacheTagCollector,
    ) {
    }

    /**
     * Lädt die Übersichtsseite mit allen aktiven Kategorien und Artikelanzahl.
     */
    public function loadOverview(Request $request, SalesChannelContext $context): KbPage
    {
        $page = $this->createPage($request, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addSorting(new FieldSorting('position', FieldSorting::ASCENDING));
        $criteria->addAssociation('articles');

        /** @var KbCategoryCollection $categories */
        $categories = $this->categoryRepository->search($criteria, $context->getContext())->getEntities();

        // Artikel pro Kategorie auf aktive filtern (für korrekte Anzahl)
        foreach ($categories as $category) {
            if ($category->getArticles() !== null) {
                $activeArticles = $category->getArticles()->filter(
                    fn ($article) => $article->isActive()
                );
                $category->setArticles(new KbArticleCollection($activeArticles->getElements()));
            }
        }

        $page->setCategories($categories);

        $this->cacheTagCollector->addTag(KbCacheTags::LISTING);

        $this->applyMeta(
            $page,
            $this->translator->trans('deskly-kb.meta.overviewTitle'),
            $this->translator->trans('deskly-kb.meta.overviewDescription'),
            '/hilfe',
            $request
        );

        return $page;
    }

    /**
     * Lädt eine Kategorie-Seite mit ihren aktiven Artikeln.
     */
    public function loadCategory(string $categorySlug, Request $request, SalesChannelContext $context): KbPage
    {
        $page = $this->createPage($request, $context);

        // Kategorie über Slug laden
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('slug', $categorySlug));
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->setLimit(1);

        $category = $this->categoryRepository->search($criteria, $context->getContext())->first();

        if ($category === null) {
            return $page;
        }

        $page->setCategory($category);

        // Aktive Artikel der Kategorie laden
        $articleCriteria = new Criteria();
        $articleCriteria->addFilter(new EqualsFilter('categoryId', $category->getId()));
        $articleCriteria->addFilter(new EqualsFilter('active', true));
        $articleCriteria->addSorting(new FieldSorting('position', FieldSorting::ASCENDING));

        /** @var KbArticleCollection $articles */
        $articles = $this->articleRepository->search($articleCriteria, $context->getContext())->getEntities();

        $page->setArticles($articles);

        $this->cacheTagCollector->addTag(KbCacheTags::LISTING, KbCacheTags::category($category->getId()));

        $this->applyCategoryMeta($page, $category, $request);

        return $page;
    }

    /**
     * Lädt einen einzelnen Artikel mit seiner Kategorie.
     */
    public function loadArticle(string $categorySlug, string $articleSlug, Request $request, SalesChannelContext $context): KbPage
    {
        $page = $this->createPage($request, $context);

        // Kategorie über Slug laden
        $categoryCriteria = new Criteria();
        $categoryCriteria->addFilter(new EqualsFilter('slug', $categorySlug));
        $categoryCriteria->addFilter(new EqualsFilter('active', true));
        $categoryCriteria->setLimit(1);

        $category = $this->categoryRepository->search($categoryCriteria, $context->getContext())->first();

        if ($category === null) {
            return $page;
        }

        $page->setCategory($category);

        // Artikel über Slug + Kategorie laden
        $articleCriteria = new Criteria();
        $articleCriteria->addFilter(new EqualsFilter('slug', $articleSlug));
        $articleCriteria->addFilter(new EqualsFilter('categoryId', $category->getId()));
        $articleCriteria->addFilter(new EqualsFilter('active', true));
        $articleCriteria->addAssociation('category');
        $articleCriteria->setLimit(1);

        $article = $this->articleRepository->search($articleCriteria, $context->getContext())->first();

        if ($article === null) {
            return $page;
        }

        $page->setArticle($article);

        $this->cacheTagCollector->addTag(
            KbCacheTags::article($article->getId()),
            KbCacheTags::category($category->getId())
        );

        $this->applyArticleMeta($page, $article, $category, $request);

        return $page;
    }

    private function createPage(Request $request, SalesChannelContext $context): KbPage
    {
        $page = $this->genericPageLoader->load($request, $context);

        $kbPage = new KbPage();

        if ($page->getMetaInformation() !== null) {
            $kbPage->setMetaInformation($page->getMetaInformation());
        }

        return $kbPage;
    }

    private function applyArticleMeta(KbPage $page, KbArticleEntity $article, KbCategoryEntity $category, Request $request): void
    {
        $title = trim((string) $article->getMetaTitle());
        $description = MetaText::description((string) $article->getMetaDescription());

        $this->applyMeta(
            $page,
            $title !== '' ? $title : MetaText::title($article->getTitle()),
            $description !== '' ? $description : MetaText::description($article->getShortText()),
            sprintf('/hilfe/%s/%s', $category->getSlug(), $article->getSlug()),
            $request
        );
    }

    private function applyCategoryMeta(KbPage $page, KbCategoryEntity $category, Request $request): void
    {
        $title = trim((string) $category->getMetaTitle());
        $description = MetaText::description((string) $category->getMetaDescription());

        if ($description === '') {
            $description = MetaText::description((string) $category->getDescription());
        }
        if ($description === '') {
            $description = $this->translator->trans('deskly-kb.meta.categoryDescription', ['%category%' => $category->getName()]);
        }

        $this->applyMeta(
            $page,
            $title !== '' ? $title : MetaText::title($this->translator->trans('deskly-kb.meta.categoryTitle', ['%category%' => $category->getName()])),
            $description,
            '/hilfe/' . $category->getSlug(),
            $request
        );
    }

    /**
     * Ohne diesen Schritt bliebe die generische MetaInformation des GenericPageLoader stehen:
     * <title>hinzke.de</title>, leere Description, kein Canonical.
     */
    private function applyMeta(KbPage $page, string $title, string $description, string $path, Request $request): void
    {
        $meta = $page->getMetaInformation() ?? new MetaInformation();

        $meta->setMetaTitle($title);
        $meta->setMetaDescription($description);

        // Canonical immer auf die Slug-URL ohne Query-Parameter
        $baseUrl = (string) $request->attributes->get(RequestTransformer::SALES_CHANNEL_ABSOLUTE_BASE_URL, '');
        if ($baseUrl === '') {
            $baseUrl = $request->getSchemeAndHttpHost();
        }
        $meta->setCanonical(rtrim($baseUrl, '/') . $path);

        $page->setMetaInformation($meta);
    }
}
