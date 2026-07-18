<?php

declare(strict_types=1);

namespace Deskly\KnowledgeBase\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1784246400AddFreescoutId extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784246400;
    }

    public function update(Connection $connection): void
    {
        $this->addFreescoutIdColumn($connection, 'deskly_kb_category');
        $this->addFreescoutIdColumn($connection, 'deskly_kb_article');
    }

    private function addFreescoutIdColumn(Connection $connection, string $table): void
    {
        $column = $connection->fetchOne(
            sprintf("SHOW COLUMNS FROM `%s` LIKE 'freescout_id'", $table)
        );

        if ($column !== false) {
            return;
        }

        $connection->executeStatement(sprintf(
            'ALTER TABLE `%s`
                ADD COLUMN `freescout_id` INT NULL,
                ADD UNIQUE KEY `uniq.%s.freescout_id` (`freescout_id`)',
            $table,
            $table
        ));
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
