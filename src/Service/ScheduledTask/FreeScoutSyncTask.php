<?php

declare(strict_types=1);

namespace Deskly\KnowledgeBase\Service\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class FreeScoutSyncTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'deskly_kb.freescout_sync';
    }

    public static function getDefaultInterval(): int
    {
        return 1800; // alle 30 Minuten
    }
}
