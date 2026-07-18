<?php

declare(strict_types=1);

namespace Deskly\KnowledgeBase\Service;

use Deskly\KnowledgeBase\Content\KbArticle\KbArticleEntity;
use Deskly\KnowledgeBase\Content\KbCategory\KbCategoryEntity;
use Deskly\KnowledgeBase\Util\SlugGenerator;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Spiegelt die FreeScout-Knowledgebase (Single Source of Truth) nach Deskly.
 *
 * Ablauf: Export abrufen → Kategorien-Upsert → Artikel-Upsert (inkl. Link-Rewrite,
 * sobald alle Ziel-Slugs bekannt sind) → verschwundene Artikel deaktivieren.
 * Eine Safety-Fuse verhindert, dass ein fehlerhafter Export die Live-Hilfe leert.
 */
class FreeScoutSyncService
{
    private const CONFIG_PREFIX = 'DesklyKnowledgeBase.config.';
    private const DEFAULT_EXPORT_URL = 'https://inbox.hinzke.de';
    private const DEFAULT_MAILBOX_ID = 4;
    private const REQUEST_TIMEOUT = 15.0;

    /** Abbruch, wenn mehr als dieser Anteil der aktuell aktiven Artikel deaktiviert würde */
    private const DEACTIVATION_FUSE_RATIO = 0.3;

    private const META_TITLE_SUFFIX = ' | Druckerei Hinzke Lübeck';
    private const META_TITLE_MAX_LENGTH = 60;
    private const META_DESCRIPTION_MAX_LENGTH = 155;
    private const SHORT_TEXT_MAX_LENGTH = 300;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly SystemConfigService $systemConfigService,
        private readonly EntityRepository $categoryRepository,
        private readonly EntityRepository $articleRepository,
        private readonly ContentSanitizer $sanitizer,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{
     *     success: bool,
     *     dryRun: bool,
     *     error: ?string,
     *     counts: array{created: int, updated: int, deactivated: int, unchanged: int, orphans: int, warnings: int},
     *     categories: array{created: int, updated: int, unchanged: int},
     *     articles: array{created: int, updated: int, unchanged: int, deactivated: int},
     *     warnings: list<string>,
     *     orphans: list<array{id: string, title: string, slug: string}>
     * }
     */
    public function sync(bool $dryRun = false, bool $force = false): array
    {
        $report = [
            'success' => true,
            'dryRun' => $dryRun,
            'error' => null,
            'counts' => [
                'created' => 0,
                'updated' => 0,
                'deactivated' => 0,
                'unchanged' => 0,
                'orphans' => 0,
                'warnings' => 0,
            ],
            'categories' => ['created' => 0, 'updated' => 0, 'unchanged' => 0],
            'articles' => ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'deactivated' => 0],
            'warnings' => [],
            'orphans' => [],
        ];

        $export = $this->fetchExport($report);

        if ($export === null) {
            // Fehler beim Abruf → Abbruch, Bestand bleibt unangetastet
            return $this->finalizeReport($report);
        }

        $context = Context::createDefaultContext();

        /** @var KbCategoryEntity[] $existingCategories */
        $existingCategories = array_values(
            $this->categoryRepository->search(new Criteria(), $context)->getEntities()->getElements()
        );

        /** @var KbArticleEntity[] $existingArticles */
        $existingArticles = array_values(
            $this->articleRepository->search(new Criteria(), $context)->getEntities()->getElements()
        );

        $categoryPlan = $this->planCategories($export['categories'], $existingCategories, $report);
        $articlePlan = $this->planArticles($export, $categoryPlan['map'], $existingArticles, $report);

        // ---- Safety-Fuse: verhindert "FreeScout-Artikel auf Entwurf → Sync leert die Live-Hilfe" ----
        $currentlyActive = 0;
        foreach ($existingArticles as $article) {
            if ($article->isActive()) {
                ++$currentlyActive;
            }
        }

        if (
            !$force
            && $currentlyActive > 0
            && $articlePlan['deactivationCount'] > $currentlyActive * self::DEACTIVATION_FUSE_RATIO
        ) {
            $message = sprintf(
                'Safety-Fuse ausgelöst: %d von %d aktiven Artikeln würden deaktiviert (Limit: %d %%). Abbruch – mit force übergehen.',
                $articlePlan['deactivationCount'],
                $currentlyActive,
                (int) round(self::DEACTIVATION_FUSE_RATIO * 100),
            );

            $report['success'] = false;
            $report['error'] = $message;
            $this->logger->error('[DesklyKB] ' . $message);

            return $this->finalizeReport($report);
        }

        if ($dryRun) {
            return $this->finalizeReport($report);
        }

        // ---- Schreiben: erst Kategorien, dann Artikel, zuletzt Deaktivierungen ----
        if ($categoryPlan['creates'] !== []) {
            $this->categoryRepository->create($categoryPlan['creates'], $context);
        }
        if ($categoryPlan['updates'] !== []) {
            $this->categoryRepository->update($categoryPlan['updates'], $context);
        }
        if ($articlePlan['updates'] !== []) {
            $this->articleRepository->update($articlePlan['updates'], $context);
        }
        if ($articlePlan['creates'] !== []) {
            $this->articleRepository->create($articlePlan['creates'], $context);
        }
        if ($articlePlan['deactivations'] !== []) {
            $this->articleRepository->update($articlePlan['deactivations'], $context);
        }

        $report = $this->finalizeReport($report);
        $this->logger->info('[DesklyKB] FreeScout-Sync abgeschlossen.', $report['counts']);

        return $report;
    }

    // ---- Export abrufen ----

    private function fetchExport(array &$report): ?array
    {
        $baseUrl = rtrim(trim((string) $this->systemConfigService->get(self::CONFIG_PREFIX . 'freescoutExportUrl')), '/');
        if ($baseUrl === '') {
            $baseUrl = self::DEFAULT_EXPORT_URL;
        }

        $mailboxId = (int) $this->systemConfigService->get(self::CONFIG_PREFIX . 'freescoutMailboxId');
        if ($mailboxId <= 0) {
            $mailboxId = self::DEFAULT_MAILBOX_ID;
        }

        $token = trim((string) $this->systemConfigService->get(self::CONFIG_PREFIX . 'freescoutExportToken'));
        if ($token === '') {
            return $this->failFetch($report, 'Kein FreeScout-Export-Token konfiguriert (Plugin-Einstellungen).');
        }

        $url = sprintf('%s/api/hinzke-kb-export/%d', $baseUrl, $mailboxId);

        try {
            $response = $this->httpClient->request('GET', $url, [
                'query' => ['token' => $token],
                'timeout' => self::REQUEST_TIMEOUT,
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode !== 200) {
                return $this->failFetch($report, sprintf('FreeScout-Export lieferte HTTP %d (%s).', $statusCode, $url));
            }

            $data = $response->toArray(false);
        } catch (\Throwable $exception) {
            // Token aus der Fehlermeldung maskieren (steckt ggf. in der URL der Exception)
            $message = str_replace($token, '***', $exception->getMessage());

            return $this->failFetch($report, sprintf('FreeScout-Export fehlgeschlagen (%s): %s', $url, $message));
        }

        if (!isset($data['categories'], $data['articles']) || !\is_array($data['categories']) || !\is_array($data['articles'])) {
            return $this->failFetch($report, 'FreeScout-Export hat ein unerwartetes Format (categories/articles fehlen).');
        }

        return $data;
    }

    private function failFetch(array &$report, string $message): ?array
    {
        $report['success'] = false;
        $report['error'] = $message;
        $this->logger->error('[DesklyKB] ' . $message);

        return null;
    }

    // ---- Kategorien ----

    /**
     * @param KbCategoryEntity[] $existingCategories
     *
     * @return array{
     *     creates: list<array<string, mixed>>,
     *     updates: list<array<string, mixed>>,
     *     map: array<int, array{id: string, slug: string, active: bool}>
     * }
     */
    private function planCategories(array $exportCategories, array $existingCategories, array &$report): array
    {
        $byFreescoutId = [];
        $adoptableByName = [];
        $slugIndex = [];

        foreach ($existingCategories as $category) {
            $slugIndex[$category->getSlug()] = true;

            if ($category->getFreescoutId() !== null) {
                $byFreescoutId[$category->getFreescoutId()] = $category;
            } else {
                $adoptableByName[mb_strtolower(trim($category->getName()), 'UTF-8')][] = $category;
            }
        }

        $creates = [];
        $updates = [];
        $map = [];

        foreach ($exportCategories as $exportCategory) {
            $freescoutId = (int) ($exportCategory['id'] ?? 0);
            $name = trim((string) ($exportCategory['name'] ?? ''));

            if ($freescoutId <= 0 || $name === '') {
                $report['warnings'][] = sprintf(
                    'Kategorie ohne ID oder Name im Export übersprungen (ID: %s).',
                    (string) ($exportCategory['id'] ?? '?')
                );
                continue;
            }

            // Beschreibung sanitisieren – wird im Storefront mit |raw gerendert (XSS-Schutz)
            $description = trim((string) ($exportCategory['description'] ?? ''));
            $description = $description === '' ? null : $this->sanitizer->sanitize($description);
            $position = (int) ($exportCategory['sort_order'] ?? 0);
            $active = (int) ($exportCategory['visibility'] ?? 1) === 1;

            $existing = $byFreescoutId[$freescoutId] ?? null;
            $adopted = false;

            // Adoption: bestehende Kategorie ohne freescout_id per Name übernehmen
            if ($existing === null) {
                $normalizedName = mb_strtolower($name, 'UTF-8');

                if (!empty($adoptableByName[$normalizedName])) {
                    $existing = array_shift($adoptableByName[$normalizedName]);
                    $adopted = true;
                }
            }

            if ($existing !== null) {
                // Vorhandener Slug wird NIE geändert (SEO)
                $map[$freescoutId] = ['id' => $existing->getId(), 'slug' => $existing->getSlug(), 'active' => $active];

                $existingDescription = $existing->getDescription();
                $existingDescription = $existingDescription === '' ? null : $existingDescription;

                $payload = ['id' => $existing->getId()];

                if ($adopted) {
                    $payload['freescoutId'] = $freescoutId;
                }
                if ($existing->getName() !== $name) {
                    $payload['name'] = $name;
                }
                if ($existingDescription !== $description) {
                    $payload['description'] = $description;
                }
                if ($existing->getPosition() !== $position) {
                    $payload['position'] = $position;
                }
                if ($existing->isActive() !== $active) {
                    $payload['active'] = $active;
                }

                if (\count($payload) > 1) {
                    $updates[] = $payload;
                    ++$report['categories']['updated'];
                } else {
                    ++$report['categories']['unchanged'];
                }

                continue;
            }

            $slug = $this->uniqueSlug(SlugGenerator::slugify($name), $slugIndex, 'kategorie-' . $freescoutId);
            $slugIndex[$slug] = true;

            $id = Uuid::randomHex();
            $creates[] = [
                'id' => $id,
                'freescoutId' => $freescoutId,
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'position' => $position,
                'active' => $active,
            ];

            $map[$freescoutId] = ['id' => $id, 'slug' => $slug, 'active' => $active];
            ++$report['categories']['created'];
        }

        return ['creates' => $creates, 'updates' => $updates, 'map' => $map];
    }

    // ---- Artikel ----

    /**
     * @param array<int, array{id: string, slug: string, active: bool}> $categoryMap
     * @param KbArticleEntity[] $existingArticles
     *
     * @return array{
     *     creates: list<array<string, mixed>>,
     *     updates: list<array<string, mixed>>,
     *     deactivations: list<array<string, mixed>>,
     *     deactivationCount: int
     * }
     */
    private function planArticles(array $export, array $categoryMap, array $existingArticles, array &$report): array
    {
        $statusPublished = (int) ($export['status_published'] ?? 2);

        $byFreescoutId = [];
        $adoptableBySlug = [];
        $slugOwner = [];

        foreach ($existingArticles as $article) {
            $slugOwner[$article->getSlug()] = $article->getId();

            if ($article->getFreescoutId() !== null) {
                $byFreescoutId[$article->getFreescoutId()] = $article;
            } else {
                $adoptableBySlug[$article->getSlug()] = $article;
            }
        }

        // ---- Pass 1: Export-Artikel zuordnen (Match per freescout_id, sonst Adoption per Slug) ----
        $matches = [];

        foreach ($export['articles'] as $exportArticle) {
            $freescoutId = (int) ($exportArticle['id'] ?? 0);

            if ($freescoutId <= 0) {
                $report['warnings'][] = 'Artikel ohne ID im Export übersprungen.';
                continue;
            }

            $existing = $byFreescoutId[$freescoutId] ?? null;
            $adopted = false;

            $exportSlug = trim((string) ($exportArticle['slug'] ?? ''));

            if ($existing === null && $exportSlug !== '' && isset($adoptableBySlug[$exportSlug])) {
                $existing = $adoptableBySlug[$exportSlug];
                unset($adoptableBySlug[$exportSlug]);
                $adopted = true;
            }

            $matches[$freescoutId] = ['data' => $exportArticle, 'existing' => $existing, 'adopted' => $adopted];
        }

        // ---- Pass 2: Slugs auflösen, Felder vorbereiten, Ziel-URLs sammeln ----
        $plannedSlugs = [];
        $planned = [];
        $urlMap = [];

        foreach ($matches as $freescoutId => $match) {
            $exportArticle = $match['data'];
            /** @var KbArticleEntity|null $existing */
            $existing = $match['existing'];

            $title = trim((string) ($exportArticle['title'] ?? ''));

            if ($title === '') {
                $report['warnings'][] = sprintf('Artikel %d hat keinen Titel – übersprungen.', $freescoutId);
                continue;
            }

            $categoryIds = array_values(array_map('intval', (array) ($exportArticle['category_ids'] ?? [])));

            if ($categoryIds === []) {
                $report['warnings'][] = sprintf('Artikel %d ("%s") hat keine Kategorie – übersprungen.', $freescoutId, $title);
                continue;
            }

            // Die ERSTE category_id ist die Hauptkategorie (Pivot-sort_order)
            $mainCategory = $categoryMap[$categoryIds[0]] ?? null;

            if ($mainCategory === null) {
                $report['warnings'][] = sprintf(
                    'Artikel %d ("%s"): Hauptkategorie %d nicht im Export – übersprungen.',
                    $freescoutId,
                    $title,
                    $categoryIds[0]
                );
                continue;
            }

            $slug = $this->resolveArticleSlug(
                trim((string) ($exportArticle['slug'] ?? '')),
                $freescoutId,
                $title,
                $existing,
                $plannedSlugs,
                $slugOwner,
                $report
            );
            $plannedSlugs[$slug] = $freescoutId;

            // Tags: Deskly-Slugs ALLER zugeordneten Kategorien (in Pivot-Reihenfolge)
            $tags = [];
            foreach ($categoryIds as $categoryId) {
                if (isset($categoryMap[$categoryId])) {
                    $tags[] = $categoryMap[$categoryId]['slug'];
                } else {
                    $report['warnings'][] = sprintf(
                        'Artikel %d ("%s"): Kategorie %d nicht im Export – Tag übersprungen.',
                        $freescoutId,
                        $title,
                        $categoryId
                    );
                }
            }

            $planned[$freescoutId] = [
                'existing' => $existing,
                'adopted' => $match['adopted'],
                'title' => $title,
                'slug' => $slug,
                'categoryId' => $mainCategory['id'],
                'tags' => $tags,
                'position' => (int) ($exportArticle['sort_order'] ?? 0),
                'active' => (int) ($exportArticle['status'] ?? 0) === $statusPublished && $mainCategory['active'],
                'content' => $this->sanitizer->sanitize((string) ($exportArticle['text'] ?? '')),
            ];

            $urlMap[$freescoutId] = sprintf('/hilfe/%s/%s', $mainCategory['slug'], $slug);
        }

        // ---- Pass 3: KB-Links umschreiben (jetzt sind alle Ziel-Slugs bekannt) ----
        $resolver = static fn (int $linkedArticleId): ?string => $urlMap[$linkedArticleId] ?? null;

        foreach ($planned as $freescoutId => &$plannedArticle) {
            $result = $this->sanitizer->rewriteLinks($plannedArticle['content'], $resolver);
            $plannedArticle['content'] = $result['html'];

            foreach ($result['warnings'] as $warning) {
                $report['warnings'][] = sprintf('Artikel %d ("%s"): %s', $freescoutId, $plannedArticle['title'], $warning);
            }
        }
        unset($plannedArticle);

        // ---- Pass 4: Payloads bauen ----
        $creates = [];
        $updates = [];
        $statusDeactivations = 0;

        foreach ($planned as $freescoutId => $plannedArticle) {
            /** @var KbArticleEntity|null $existing */
            $existing = $plannedArticle['existing'];

            $plainText = $this->sanitizer->toPlainText($plannedArticle['content']);
            $shortText = $this->buildShortText($plainText, $plannedArticle['title']);

            if ($existing === null) {
                $creates[] = [
                    'id' => Uuid::randomHex(),
                    'freescoutId' => $freescoutId,
                    'categoryId' => $plannedArticle['categoryId'],
                    'title' => $plannedArticle['title'],
                    'slug' => $plannedArticle['slug'],
                    'metaTitle' => $this->buildMetaTitle($plannedArticle['title']),
                    'metaDescription' => $this->buildMetaDescription($plainText),
                    'shortText' => $shortText,
                    'content' => $plannedArticle['content'],
                    'tags' => $plannedArticle['tags'],
                    'position' => $plannedArticle['position'],
                    'active' => $plannedArticle['active'],
                ];
                ++$report['articles']['created'];

                continue;
            }

            if ($existing->isActive() && !$plannedArticle['active']) {
                // Zählt für die Safety-Fuse (z. B. Artikel in FreeScout auf Entwurf gestellt)
                ++$statusDeactivations;
            }

            $payload = ['id' => $existing->getId()];

            if ($plannedArticle['adopted']) {
                $payload['freescoutId'] = $freescoutId;
            }
            if ($existing->getTitle() !== $plannedArticle['title']) {
                $payload['title'] = $plannedArticle['title'];
            }
            if ($existing->getSlug() !== $plannedArticle['slug']) {
                $payload['slug'] = $plannedArticle['slug'];
            }
            if ($existing->getCategoryId() !== $plannedArticle['categoryId']) {
                $payload['categoryId'] = $plannedArticle['categoryId'];
            }
            if ($existing->getContent() !== $plannedArticle['content']) {
                $payload['content'] = $plannedArticle['content'];
            }
            if (($existing->getTags() ?? []) !== $plannedArticle['tags']) {
                $payload['tags'] = $plannedArticle['tags'];
            }
            if ($existing->getPosition() !== $plannedArticle['position']) {
                $payload['position'] = $plannedArticle['position'];
            }
            if ($existing->isActive() !== $plannedArticle['active']) {
                $payload['active'] = $plannedArticle['active'];
            }
            if ($existing->getShortText() !== $shortText) {
                $payload['shortText'] = $shortText;
            }

            // Meta-Felder NUR setzen, wenn aktuell leer (manuell gepflegte Werte nicht überschreiben)
            if (($existing->getMetaTitle() ?? '') === '') {
                $payload['metaTitle'] = $this->buildMetaTitle($plannedArticle['title']);
            }
            if (($existing->getMetaDescription() ?? '') === '') {
                $metaDescription = $this->buildMetaDescription($plainText);

                if ($metaDescription !== null) {
                    $payload['metaDescription'] = $metaDescription;
                }
            }

            if (\count($payload) > 1) {
                $updates[] = $payload;
                ++$report['articles']['updated'];
            } else {
                ++$report['articles']['unchanged'];
            }
        }

        // ---- Pass 5: Artikel mit freescout_id, die nicht mehr im Export sind → deaktivieren ----
        $deactivations = [];

        foreach ($byFreescoutId as $freescoutId => $article) {
            if (isset($matches[$freescoutId])) {
                continue;
            }

            if ($article->isActive()) {
                $deactivations[] = ['id' => $article->getId(), 'active' => false];
                ++$report['articles']['deactivated'];
                $report['warnings'][] = sprintf(
                    'Artikel "%s" (FreeScout-ID %d) nicht mehr im Export – wird deaktiviert.',
                    $article->getTitle(),
                    $freescoutId
                );
            }
        }

        // ---- Waisen: Artikel ohne freescout_id, die nicht adoptiert wurden → nicht anfassen, nur listen ----
        foreach ($adoptableBySlug as $article) {
            $report['orphans'][] = [
                'id' => $article->getId(),
                'title' => $article->getTitle(),
                'slug' => $article->getSlug(),
            ];
        }

        return [
            'creates' => $creates,
            'updates' => $updates,
            'deactivations' => $deactivations,
            'deactivationCount' => \count($deactivations) + $statusDeactivations,
        ];
    }

    /**
     * Löst den Ziel-Slug eines Artikels auf. Bei Kollision mit einem ANDEREN Artikel
     * wird -2/-3/... angehängt und eine Warnung in den Report geschrieben.
     *
     * @param array<string, int> $plannedSlugs in diesem Lauf bereits vergebene Slugs (Slug → FreeScout-ID)
     * @param array<string, string> $slugOwner bestehende Slugs (Slug → Deskly-Entity-ID)
     */
    private function resolveArticleSlug(
        string $desiredSlug,
        int $freescoutId,
        string $title,
        ?KbArticleEntity $existing,
        array $plannedSlugs,
        array $slugOwner,
        array &$report,
    ): string {
        $base = SlugGenerator::slugify($desiredSlug);

        if ($base === '') {
            $base = SlugGenerator::slugify($title);
        }
        if ($base === '') {
            $base = 'artikel-' . $freescoutId;
        }

        $candidate = $base;
        $counter = 1;

        while ($this->isSlugTaken($candidate, $freescoutId, $existing, $plannedSlugs, $slugOwner)) {
            ++$counter;
            $candidate = $base . '-' . $counter;
        }

        if ($candidate !== $base) {
            $report['warnings'][] = sprintf(
                'Artikel %d ("%s"): Slug "%s" kollidiert mit anderem Artikel – "%s" verwendet.',
                $freescoutId,
                $title,
                $base,
                $candidate
            );
        }

        return $candidate;
    }

    /**
     * @param array<string, int> $plannedSlugs
     * @param array<string, string> $slugOwner
     */
    private function isSlugTaken(
        string $candidate,
        int $freescoutId,
        ?KbArticleEntity $existing,
        array $plannedSlugs,
        array $slugOwner,
    ): bool {
        // In diesem Lauf bereits von einem anderen Export-Artikel geplant?
        if (isset($plannedSlugs[$candidate]) && $plannedSlugs[$candidate] !== $freescoutId) {
            return true;
        }

        if (!isset($slugOwner[$candidate])) {
            return false;
        }

        // Der eigene Slug ist keine Kollision
        return $existing === null || $slugOwner[$candidate] !== $existing->getId();
    }

    /**
     * Liefert einen Slug, der nicht in $taken vorkommt. Leere Basis → Fallback.
     * Bei Kollision wird -2/-3/... angehängt (SEO-stabile Basis bleibt erhalten).
     *
     * @param array<string, true> $taken bereits vergebene Slugs (inkl. Bestands-Slugs)
     */
    private function uniqueSlug(string $base, array $taken, string $fallback): string
    {
        if ($base === '') {
            $base = SlugGenerator::slugify($fallback);
        }
        if ($base === '') {
            $base = $fallback;
        }

        $candidate = $base;
        $counter = 1;

        while (isset($taken[$candidate])) {
            ++$counter;
            $candidate = $base . '-' . $counter;
        }

        return $candidate;
    }

    // ---- Text-Aufbereitung ----

    private function buildShortText(string $plainText, string $fallback): string
    {
        $paragraphs = preg_split('/\n\s*\n/', $plainText) ?: [];
        $firstParagraph = '';

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim((string) preg_replace('/\s+/', ' ', $paragraph));

            if ($paragraph !== '') {
                $firstParagraph = $paragraph;
                break;
            }
        }

        if ($firstParagraph === '') {
            return $fallback;
        }

        return $this->truncateAtWordBoundary($firstParagraph, self::SHORT_TEXT_MAX_LENGTH);
    }

    private function buildMetaTitle(string $title): string
    {
        $full = $title . self::META_TITLE_SUFFIX;

        if (mb_strlen($full, 'UTF-8') <= self::META_TITLE_MAX_LENGTH) {
            return $full;
        }

        if (mb_strlen($title, 'UTF-8') <= self::META_TITLE_MAX_LENGTH) {
            return $title;
        }

        return $this->truncateAtWordBoundary($title, self::META_TITLE_MAX_LENGTH);
    }

    private function buildMetaDescription(string $plainText): ?string
    {
        $flat = trim((string) preg_replace('/\s+/', ' ', $plainText));

        if ($flat === '') {
            return null;
        }

        return $this->truncateAtWordBoundary($flat, self::META_DESCRIPTION_MAX_LENGTH);
    }

    private function truncateAtWordBoundary(string $text, int $maxLength): string
    {
        if (mb_strlen($text, 'UTF-8') <= $maxLength) {
            return $text;
        }

        $cut = mb_substr($text, 0, $maxLength, 'UTF-8');
        $lastSpace = mb_strrpos($cut, ' ', 0, 'UTF-8');

        if ($lastSpace !== false && $lastSpace > $maxLength * 0.5) {
            $cut = mb_substr($cut, 0, $lastSpace, 'UTF-8');
        }

        return rtrim($cut, " \t-,.;:");
    }

    private function finalizeReport(array $report): array
    {
        $report['counts']['created'] = $report['categories']['created'] + $report['articles']['created'];
        $report['counts']['updated'] = $report['categories']['updated'] + $report['articles']['updated'];
        $report['counts']['unchanged'] = $report['categories']['unchanged'] + $report['articles']['unchanged'];
        $report['counts']['deactivated'] = $report['articles']['deactivated'];
        $report['counts']['orphans'] = \count($report['orphans']);
        $report['counts']['warnings'] = \count($report['warnings']);

        return $report;
    }
}
