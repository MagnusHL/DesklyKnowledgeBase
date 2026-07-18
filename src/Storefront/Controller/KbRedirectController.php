<?php

declare(strict_types=1);

namespace Deskly\KnowledgeBase\Storefront\Controller;

use Deskly\KnowledgeBase\Content\KbArticle\KbArticleEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class KbRedirectController extends StorefrontController
{
    public function __construct(
        private readonly EntityRepository $articleRepository,
    ) {
    }

    /**
     * Leitet FreeScout-Hilfe-Artikel-URLs per 301 auf die Deskly-Artikel-URL um.
     * Deckt beide Custom-Domain-Varianten ab:
     *   - hinzke.de/hc/{mailboxId}/{articleId}/{slug}          (Domain = hinzke.de)
     *   - hinzke.de/hilfe/hc/{mailboxId}/{articleId}/{slug}    (Domain = hinzke.de/hilfe)
     * Die /hilfe/hc-Route bekommt hohe Priorität, damit sie nicht von
     * /hilfe/{categorySlug}/{articleSlug} geschluckt wird. category_id-Query wird ignoriert.
     */
    #[Route(
        path: '/hc/{mailboxId}/{articleId}/{slug?}',
        name: 'frontend.kb.freescout.redirect',
        requirements: ['mailboxId' => '\d+', 'articleId' => '\d+[^/]*', 'slug' => '.*'],
        methods: ['GET'],
    )]
    #[Route(
        path: '/hilfe/hc/{mailboxId}/{articleId}/{slug?}',
        name: 'frontend.kb.freescout.redirect.hilfe',
        requirements: ['mailboxId' => '\d+', 'articleId' => '\d+[^/]*', 'slug' => '.*'],
        methods: ['GET'],
        priority: 100,
    )]
    public function redirectFreescoutArticle(string $mailboxId, string $articleId, ?string $slug, SalesChannelContext $context): Response
    {
        // Führende Ziffern extrahieren – deckt auch das Format {articleId}-{slug} ab
        $freescoutId = (int) $articleId;

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('freescoutId', $freescoutId));
        $criteria->addAssociation('category');
        $criteria->setLimit(1);

        /** @var KbArticleEntity|null $article */
        $article = $this->articleRepository->search($criteria, $context->getContext())->first();

        if (
            $article === null
            || !$article->isActive()
            || $article->getCategory() === null
            || !$article->getCategory()->isActive()
        ) {
            return $this->redirectToRoute('frontend.kb.overview', [], Response::HTTP_MOVED_PERMANENTLY);
        }

        return $this->redirectToRoute('frontend.kb.article', [
            'categorySlug' => $article->getCategory()->getSlug(),
            'articleSlug' => $article->getSlug(),
        ], Response::HTTP_MOVED_PERMANENTLY);
    }

    /**
     * Leitet die FreeScout-KB-Startseite (/hc/{mailboxId} bzw. /hilfe/hc/{mailboxId})
     * auf die Deskly-Hilfe-Übersicht um.
     */
    #[Route(
        path: '/hc/{mailboxId}',
        name: 'frontend.kb.freescout.home',
        requirements: ['mailboxId' => '\d+'],
        methods: ['GET'],
    )]
    #[Route(
        path: '/hilfe/hc/{mailboxId}',
        name: 'frontend.kb.freescout.home.hilfe',
        requirements: ['mailboxId' => '\d+'],
        methods: ['GET'],
        priority: 100,
    )]
    public function redirectFreescoutHome(): Response
    {
        return $this->redirectToRoute('frontend.kb.overview', [], Response::HTTP_MOVED_PERMANENTLY);
    }
}
