<?php

declare(strict_types=1);

namespace Deskly\KnowledgeBase\Storefront\Page;

use Deskly\KnowledgeBase\Content\KbArticle\KbArticleCollection;
use Deskly\KnowledgeBase\Content\KbCategory\KbCategoryCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\HttpFoundation\Request;

class KbPageLoader
{
    public function __construct(
        private readonly EntityRepository $categoryRepository,
        private readonly EntityRepository $articleRepository,
        private readonly GenericPageLoaderInterface $genericPageLoader,
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

        return $page;
    }

    private function createPage(Request $request, SalesChannelContext $context): KbPage
    {
        $page = $this->genericPageLoader->load($request, $context);

        $kbPage = new KbPage();
        $kbPage->setHeader($page->getHeader());
        $kbPage->setFooter($page->getFooter());

        return $kbPage;
    }
}
