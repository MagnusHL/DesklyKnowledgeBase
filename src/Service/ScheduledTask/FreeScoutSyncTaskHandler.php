<?php

declare(strict_types=1);

namespace Deskly\KnowledgeBase\Service\ScheduledTask;

use Deskly\KnowledgeBase\Service\FreeScoutSyncService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: FreeScoutSyncTask::class)]
class FreeScoutSyncTaskHandler extends ScheduledTaskHandler
{
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly FreeScoutSyncService $syncService,
        private readonly SystemConfigService $systemConfigService,
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        // Automatischer Sync nur, wenn er in den Plugin-Einstellungen aktiviert ist
        if (!(bool) $this->systemConfigService->get('DesklyKnowledgeBase.config.syncEnabled')) {
            return;
        }

        $this->syncService->sync();
    }
}
