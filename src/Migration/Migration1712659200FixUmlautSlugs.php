<?php

declare(strict_types=1);

namespace Deskly\KnowledgeBase\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1712659200FixUmlautSlugs extends MigrationStep
{
    private const TRANSLITERATION_MAP = [
        'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
        'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'á' => 'a', 'à' => 'a', 'â' => 'a',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u',
        'í' => 'i', 'ì' => 'i', 'î' => 'i',
        'ñ' => 'n', 'ç' => 'c',
    ];

    public function getCreationTimestamp(): int
    {
        return 1712659200;
    }

    public function update(Connection $connection): void
    {
        $this->fixSlugs($connection, 'deskly_kb_article', 'title');
        $this->fixSlugs($connection, 'deskly_kb_category', 'name');
    }

    private function fixSlugs(Connection $connection, string $table, string $sourceColumn): void
    {
        $rows = $connection->fetchAllAssociative(
            sprintf('SELECT id, `%s` AS source, slug FROM `%s`', $sourceColumn, $table)
        );

        foreach ($rows as $row) {
            $newSlug = $this->slugify($row['source']);

            if ($newSlug !== $row['slug']) {
                // Unique-Constraint: bei Duplikaten Suffix anhängen
                $finalSlug = $this->ensureUnique($connection, $table, $newSlug, $row['id']);

                $connection->executeStatement(
                    sprintf('UPDATE `%s` SET slug = ? WHERE id = ?', $table),
                    [$finalSlug, $row['id']]
                );
            }
        }
    }

    private function ensureUnique(Connection $connection, string $table, string $slug, mixed $currentId): string
    {
        $candidate = $slug;
        $counter = 1;

        while (true) {
            $existing = $connection->fetchOne(
                sprintf('SELECT id FROM `%s` WHERE slug = ? AND id != ?', $table),
                [$candidate, $currentId]
            );

            if ($existing === false) {
                return $candidate;
            }

            ++$counter;
            $candidate = $slug . '-' . $counter;
        }
    }

    private function slugify(string $text): string
    {
        $text = strtr($text, self::TRANSLITERATION_MAP);
        $text = mb_strtolower($text, 'UTF-8');
        $text = (string) preg_replace('/[^a-z0-9]+/', '-', $text);
        $text = (string) preg_replace('/-{2,}/', '-', $text);
        $text = trim($text, '-');

        if (\strlen($text) > 200) {
            $text = substr($text, 0, 200);
            $lastHyphen = strrpos($text, '-');
            if ($lastHyphen !== false && $lastHyphen > 120) {
                $text = substr($text, 0, $lastHyphen);
            }
        }

        return $text;
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
