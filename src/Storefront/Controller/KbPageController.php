<?php

declare(strict_types=1);

namespace Deskly\KnowledgeBase\Storefront\Controller;

use Deskly\KnowledgeBase\Storefront\Page\KbPageLoader;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class KbPageController extends StorefrontController
{
    public function __construct(
        private readonly KbPageLoader $pageLoader,
    ) {
    }

    #[Route(
        path: '/hilfe',
        name: 'frontend.kb.overview',
        defaults: ['_httpCache' => true],
        methods: ['GET'],
    )]
    public function overview(Request $request, SalesChannelContext $context): Response
    {
        $page = $this->pageLoader->loadOverview($request, $context);

        return $this->renderStorefront(
            '@DesklyKnowledgeBase/storefront/page/kb-overview.html.twig',
            ['page' => $page]
        );
    }

    #[Route(
        path: '/hilfe/{categorySlug}',
        name: 'frontend.kb.category',
        defaults: ['_httpCache' => true],
        methods: ['GET'],
    )]
    public function category(string $categorySlug, Request $request, SalesChannelContext $context): Response
    {
        $page = $this->pageLoader->loadCategory($categorySlug, $request, $context);

        if ($page->getCategory() === null) {
            throw new NotFoundHttpException('Kategorie nicht gefunden.');
        }

        return $this->renderStorefront(
            '@DesklyKnowledgeBase/storefront/page/kb-category.html.twig',
            ['page' => $page]
        );
    }

    #[Route(
        path: '/hilfe/{categorySlug}/{articleSlug}',
        name: 'frontend.kb.article',
        defaults: ['_httpCache' => true],
        methods: ['GET'],
    )]
    public function article(string $categorySlug, string $articleSlug, Request $request, SalesChannelContext $context): Response
    {
        $page = $this->pageLoader->loadArticle($categorySlug, $articleSlug, $request, $context);

        if ($page->getArticle() === null) {
            throw new NotFoundHttpException('Artikel nicht gefunden.');
        }

        return $this->renderStorefront(
            '@DesklyKnowledgeBase/storefront/page/kb-article.html.twig',
            ['page' => $page]
        );
    }
}
