<?php

declare(strict_types=1);

namespace Deskly\KnowledgeBase\Api;

use Deskly\KnowledgeBase\Util\SlugGenerator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\CountAggregation;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
class KbAdminController extends AbstractController
{
    public function __construct(
        private readonly EntityRepository $categoryRepository,
        private readonly EntityRepository $articleRepository,
    ) {
    }

    // ---- Kategorien ----

    #[Route(
        path: '/api/deskly-kb/categories',
        name: 'api.deskly-kb.categories.list',
        methods: ['GET'],
    )]
    public function listCategories(Request $request, Context $context): JsonResponse
    {
        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('position', FieldSorting::ASCENDING));
        $criteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));
        $criteria->addAssociation('articles');

        $result = $this->categoryRepository->search($criteria, $context);

        $categories = [];
        foreach ($result->getEntities() as $category) {
            $articles = $category->getArticles();
            $categories[] = [
                'id' => $category->getId(),
                'name' => $category->getName(),
                'slug' => $category->getSlug(),
                'description' => $category->getDescription(),
                'metaTitle' => $category->getMetaTitle(),
                'metaDescription' => $category->getMetaDescription(),
                'position' => $category->getPosition(),
                'active' => $category->isActive(),
                'articleCount' => $articles !== null ? $articles->count() : 0,
                'createdAt' => $category->getCreatedAt()?->format(\DateTimeInterface::ATOM),
                'updatedAt' => $category->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
            ];
        }

        return new JsonResponse([
            'total' => $result->getTotal(),
            'data' => $categories,
        ]);
    }

    #[Route(
        path: '/api/deskly-kb/categories',
        name: 'api.deskly-kb.categories.create',
        methods: ['POST'],
    )]
    public function createCategory(Request $request, Context $context): JsonResponse
    {
        $data = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $id = $data['id'] ?? Uuid::randomHex();
        $data['id'] = $id;

        if (empty($data['slug']) && !empty($data['name'])) {
            $data['slug'] = SlugGenerator::slugify($data['name']);
        }

        $this->categoryRepository->create([$data], $context);

        return new JsonResponse(['id' => $id], Response::HTTP_CREATED);
    }

    #[Route(
        path: '/api/deskly-kb/categories/{id}',
        name: 'api.deskly-kb.categories.update',
        methods: ['PATCH'],
    )]
    public function updateCategory(string $id, Request $request, Context $context): JsonResponse
    {
        $this->assertEntityExists($this->categoryRepository, $id, $context, 'Kategorie');

        $data = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $data['id'] = $id;

        $this->categoryRepository->update([$data], $context);

        return new JsonResponse(['id' => $id]);
    }

    #[Route(
        path: '/api/deskly-kb/categories/{id}',
        name: 'api.deskly-kb.categories.delete',
        methods: ['DELETE'],
    )]
    public function deleteCategory(string $id, Context $context): JsonResponse
    {
        $this->assertEntityExists($this->categoryRepository, $id, $context, 'Kategorie');

        $this->categoryRepository->delete([['id' => $id]], $context);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    // ---- Artikel ----

    #[Route(
        path: '/api/deskly-kb/articles',
        name: 'api.deskly-kb.articles.list',
        methods: ['GET'],
    )]
    public function listArticles(Request $request, Context $context): JsonResponse
    {
        $criteria = $this->buildArticleCriteria($request);
        $criteria->addAssociation('category');

        $result = $this->articleRepository->search($criteria, $context);

        $articles = [];
        foreach ($result->getEntities() as $article) {
            $articles[] = $this->serializeArticle($article);
        }

        return new JsonResponse([
            'total' => $result->getTotal(),
            'data' => $articles,
        ]);
    }

    #[Route(
        path: '/api/deskly-kb/articles/{id}',
        name: 'api.deskly-kb.articles.detail',
        methods: ['GET'],
    )]
    public function getArticle(string $id, Context $context): JsonResponse
    {
        $criteria = new Criteria([$id]);
        $criteria->addAssociation('category');

        $article = $this->articleRepository->search($criteria, $context)->getEntities()->first();

        if ($article === null) {
            return new JsonResponse(['error' => 'Artikel nicht gefunden.'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(['data' => $this->serializeArticle($article)]);
    }

    #[Route(
        path: '/api/deskly-kb/articles',
        name: 'api.deskly-kb.articles.create',
        methods: ['POST'],
    )]
    public function createArticle(Request $request, Context $context): JsonResponse
    {
        $data = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $id = $data['id'] ?? Uuid::randomHex();
        $data['id'] = $id;

        if (empty($data['slug']) && !empty($data['title'])) {
            $data['slug'] = SlugGenerator::slugify($data['title']);
        }

        $this->articleRepository->create([$data], $context);

        return new JsonResponse(['id' => $id], Response::HTTP_CREATED);
    }

    #[Route(
        path: '/api/deskly-kb/articles/{id}',
        name: 'api.deskly-kb.articles.update',
        methods: ['PATCH'],
    )]
    public function updateArticle(string $id, Request $request, Context $context): JsonResponse
    {
        $this->assertEntityExists($this->articleRepository, $id, $context, 'Artikel');

        $data = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $data['id'] = $id;

        $this->articleRepository->update([$data], $context);

        return new JsonResponse(['id' => $id]);
    }

    #[Route(
        path: '/api/deskly-kb/articles/{id}',
        name: 'api.deskly-kb.articles.delete',
        methods: ['DELETE'],
    )]
    public function deleteArticle(string $id, Context $context): JsonResponse
    {
        $this->assertEntityExists($this->articleRepository, $id, $context, 'Artikel');

        $this->articleRepository->delete([['id' => $id]], $context);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    // ---- Hilfsmethoden ----

    private function buildArticleCriteria(Request $request): Criteria
    {
        $limit = max(1, $request->query->getInt('limit', 25));
        $page = max(1, $request->query->getInt('page', 1));

        $criteria = new Criteria();
        $criteria->setLimit($limit);
        $criteria->setOffset(($page - 1) * $limit);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);
        $criteria->addSorting(new FieldSorting('position', FieldSorting::ASCENDING));
        $criteria->addSorting(new FieldSorting('title', FieldSorting::ASCENDING));

        $categoryId = $request->query->get('categoryId');
        if ($categoryId !== null && $categoryId !== '') {
            $criteria->addFilter(new EqualsFilter('categoryId', $categoryId));
        }

        $tag = $request->query->get('tag');
        if ($tag !== null && $tag !== '') {
            $criteria->addFilter(new ContainsFilter('tags', $tag));
        }

        return $criteria;
    }

    private function serializeArticle(object $article): array
    {
        $category = $article->getCategory();

        return [
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
            'active' => $article->isActive(),
            'category' => $category !== null ? [
                'id' => $category->getId(),
                'name' => $category->getName(),
                'slug' => $category->getSlug(),
            ] : null,
            'createdAt' => $article->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'updatedAt' => $article->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    private function assertEntityExists(EntityRepository $repository, string $id, Context $context, string $label): void
    {
        $result = $repository->searchIds(new Criteria([$id]), $context);

        if ($result->getTotal() === 0) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException(
                sprintf('%s mit ID "%s" nicht gefunden.', $label, $id),
            );
        }
    }
}
