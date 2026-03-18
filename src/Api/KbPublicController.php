<?php

declare(strict_types=1);

namespace Deskly\KnowledgeBase\Api;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
class KbPublicController extends AbstractController
{
    public function __construct(
        private readonly EntityRepository $categoryRepository,
        private readonly EntityRepository $articleRepository,
    ) {
    }

    // ---- Kategorien ----

    #[Route(
        path: '/api/deskly-kb/public/categories',
        name: 'api.deskly-kb.public.categories.list',
        methods: ['GET'],
    )]
    public function listCategories(Context $context): JsonResponse
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addSorting(new FieldSorting('position', FieldSorting::ASCENDING));
        $criteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));
        $criteria->addAssociation('articles');

        $result = $this->categoryRepository->search($criteria, $context);

        $categories = [];
        foreach ($result->getEntities() as $category) {
            $articles = $category->getArticles();
            // Nur aktive Artikel zaehlen
            $activeCount = 0;
            if ($articles !== null) {
                foreach ($articles as $article) {
                    if ($article->isActive()) {
                        ++$activeCount;
                    }
                }
            }

            $categories[] = [
                'id' => $category->getId(),
                'name' => $category->getName(),
                'slug' => $category->getSlug(),
                'description' => $category->getDescription(),
                'metaTitle' => $category->getMetaTitle(),
                'metaDescription' => $category->getMetaDescription(),
                'position' => $category->getPosition(),
                'articleCount' => $activeCount,
            ];
        }

        return new JsonResponse([
            'total' => count($categories),
            'data' => $categories,
        ]);
    }

    // ---- Artikel ----

    #[Route(
        path: '/api/deskly-kb/public/articles',
        name: 'api.deskly-kb.public.articles.list',
        methods: ['GET'],
    )]
    public function listArticles(Request $request, Context $context): JsonResponse
    {
        $criteria = $this->buildPublicArticleCriteria($request);
        $criteria->addAssociation('category');

        // Volltextsuche
        $query = trim((string) $request->query->get('q', ''));
        if ($query !== '') {
            $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
                new ContainsFilter('title', $query),
                new ContainsFilter('shortText', $query),
                new ContainsFilter('content', $query),
            ]));
        }

        $result = $this->articleRepository->search($criteria, $context);

        $articles = [];
        foreach ($result->getEntities() as $article) {
            $category = $article->getCategory();
            $articles[] = [
                'id' => $article->getId(),
                'categoryId' => $article->getCategoryId(),
                'title' => $article->getTitle(),
                'slug' => $article->getSlug(),
                'metaTitle' => $article->getMetaTitle(),
                'metaDescription' => $article->getMetaDescription(),
                'shortText' => $article->getShortText(),
                'content' => $article->getContent(),
                'tags' => $article->getTags(),
                'position' => $article->getPosition(),
                'category' => $category !== null ? [
                    'id' => $category->getId(),
                    'name' => $category->getName(),
                    'slug' => $category->getSlug(),
                ] : null,
                'createdAt' => $article->getCreatedAt()?->format(\DateTimeInterface::ATOM),
                'updatedAt' => $article->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
            ];
        }

        return new JsonResponse([
            'total' => $result->getTotal(),
            'data' => $articles,
        ]);
    }

    #[Route(
        path: '/api/deskly-kb/public/articles/{slug}',
        name: 'api.deskly-kb.public.articles.detail',
        methods: ['GET'],
    )]
    public function getArticleBySlug(string $slug, Context $context): JsonResponse
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('slug', $slug));
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addAssociation('category');
        $criteria->setLimit(1);

        $article = $this->articleRepository->search($criteria, $context)->getEntities()->first();

        if ($article === null) {
            return new JsonResponse(
                ['error' => 'Artikel nicht gefunden.'],
                Response::HTTP_NOT_FOUND,
            );
        }

        $category = $article->getCategory();

        return new JsonResponse([
            'data' => [
                'id' => $article->getId(),
                'categoryId' => $article->getCategoryId(),
                'title' => $article->getTitle(),
                'slug' => $article->getSlug(),
                'metaTitle' => $article->getMetaTitle(),
                'metaDescription' => $article->getMetaDescription(),
                'shortText' => $article->getShortText(),
                'content' => $article->getContent(),
                'tags' => $article->getTags(),
                'position' => $article->getPosition(),
                'category' => $category !== null ? [
                    'id' => $category->getId(),
                    'name' => $category->getName(),
                    'slug' => $category->getSlug(),
                ] : null,
                'createdAt' => $article->getCreatedAt()?->format(\DateTimeInterface::ATOM),
                'updatedAt' => $article->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
            ],
        ]);
    }

    // ---- Suche (kompaktes Format fuer Chatbot) ----

    #[Route(
        path: '/api/deskly-kb/public/search',
        name: 'api.deskly-kb.public.search',
        methods: ['GET'],
    )]
    public function search(Request $request, Context $context): JsonResponse
    {
        $query = trim((string) $request->query->get('q', ''));

        if ($query === '') {
            return new JsonResponse([
                'total' => 0,
                'data' => [],
            ]);
        }

        $limit = max(1, $request->query->getInt('limit', 10));
        $page = max(1, $request->query->getInt('page', 1));

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new ContainsFilter('title', $query),
            new ContainsFilter('shortText', $query),
            new ContainsFilter('content', $query),
        ]));
        $criteria->addSorting(new FieldSorting('position', FieldSorting::ASCENDING));
        $criteria->addSorting(new FieldSorting('title', FieldSorting::ASCENDING));
        $criteria->setLimit($limit);
        $criteria->setOffset(($page - 1) * $limit);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);
        $criteria->addAssociation('category');

        $result = $this->articleRepository->search($criteria, $context);

        $hits = [];
        foreach ($result->getEntities() as $article) {
            $category = $article->getCategory();
            $hits[] = [
                'id' => $article->getId(),
                'title' => $article->getTitle(),
                'slug' => $article->getSlug(),
                'shortText' => $article->getShortText(),
                'categorySlug' => $category?->getSlug(),
                'tags' => $article->getTags(),
            ];
        }

        return new JsonResponse([
            'total' => $result->getTotal(),
            'data' => $hits,
        ]);
    }

    // ---- Hilfsmethoden ----

    private function buildPublicArticleCriteria(Request $request): Criteria
    {
        $limit = max(1, $request->query->getInt('limit', 25));
        $page = max(1, $request->query->getInt('page', 1));

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->setLimit($limit);
        $criteria->setOffset(($page - 1) * $limit);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);
        $criteria->addSorting(new FieldSorting('position', FieldSorting::ASCENDING));
        $criteria->addSorting(new FieldSorting('title', FieldSorting::ASCENDING));

        $categorySlug = $request->query->get('categorySlug');
        if ($categorySlug !== null && $categorySlug !== '') {
            $criteria->addFilter(new EqualsFilter('category.slug', $categorySlug));
        }

        $tag = $request->query->get('tag');
        if ($tag !== null && $tag !== '') {
            $criteria->addFilter(new ContainsFilter('tags', $tag));
        }

        return $criteria;
    }
}
