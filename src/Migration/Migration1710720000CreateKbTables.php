<?php

declare(strict_types=1);

namespace Deskly\KnowledgeBase\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1710720000CreateKbTables extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1710720000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `deskly_kb_category` (
                `id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `slug` VARCHAR(255) NOT NULL,
                `description` LONGTEXT NULL,
                `meta_title` VARCHAR(255) NULL,
                `meta_description` VARCHAR(500) NULL,
                `position` INT NOT NULL DEFAULT 0,
                `active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.deskly_kb_category.slug` (`slug`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `deskly_kb_article` (
                `id` BINARY(16) NOT NULL,
                `category_id` BINARY(16) NOT NULL,
                `title` VARCHAR(255) NOT NULL,
                `slug` VARCHAR(255) NOT NULL,
                `meta_title` VARCHAR(255) NULL,
                `meta_description` VARCHAR(500) NULL,
                `short_text` LONGTEXT NOT NULL,
                `content` LONGTEXT NOT NULL,
                `tags` JSON NULL,
                `position` INT NOT NULL DEFAULT 0,
                `active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.deskly_kb_article.slug` (`slug`),
                KEY `idx.deskly_kb_article.category_id` (`category_id`),
                CONSTRAINT `fk.deskly_kb_article.category_id`
                    FOREIGN KEY (`category_id`)
                    REFERENCES `deskly_kb_category` (`id`)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
