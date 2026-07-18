<?php

declare(strict_types=1);

namespace Deskly\KnowledgeBase\Command;

use Deskly\KnowledgeBase\Service\FreeScoutSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Manueller FreeScout-Sync. Ignoriert bewusst den syncEnabled-Schalter –
 * ein manueller Aufruf zählt als Absicht.
 */
#[AsCommand(
    name: 'deskly:kb:sync',
    description: 'Spiegelt die FreeScout-Knowledgebase nach Deskly (FreeScout ist die Quelle der Wahrheit)',
)]
class SyncCommand extends Command
{
    public function __construct(
        private readonly FreeScoutSyncService $syncService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Nur anzeigen, was passieren würde – nichts schreiben')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Safety-Fuse (Schutz vor Massen-Deaktivierung) übergehen');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $dryRun = (bool) $input->getOption('dry-run');
        $force = (bool) $input->getOption('force');

        $io->title('FreeScout → Deskly Knowledgebase Sync');

        if ($dryRun) {
            $io->note('Dry-Run: es werden keine Änderungen geschrieben.');
        }

        $report = $this->syncService->sync($dryRun, $force);

        $io->table(
            ['Kennzahl', 'Anzahl'],
            [
                ['Erstellt', $report['counts']['created']],
                ['Aktualisiert', $report['counts']['updated']],
                ['Deaktiviert', $report['counts']['deactivated']],
                ['Unverändert', $report['counts']['unchanged']],
                ['Waisen (ohne FreeScout-ID)', $report['counts']['orphans']],
                ['Warnungen', $report['counts']['warnings']],
            ]
        );

        $io->text(sprintf(
            'Kategorien: %d erstellt, %d aktualisiert, %d unverändert | Artikel: %d erstellt, %d aktualisiert, %d unverändert, %d deaktiviert',
            $report['categories']['created'],
            $report['categories']['updated'],
            $report['categories']['unchanged'],
            $report['articles']['created'],
            $report['articles']['updated'],
            $report['articles']['unchanged'],
            $report['articles']['deactivated'],
        ));

        if ($report['warnings'] !== []) {
            $io->section('Warnungen');
            $io->listing($report['warnings']);
        }

        if ($report['orphans'] !== []) {
            $io->section('Waisen (Deskly-Artikel ohne FreeScout-Zuordnung, bleiben unangetastet)');
            $io->listing(array_map(
                static fn (array $orphan): string => sprintf('%s (Slug: %s)', $orphan['title'], $orphan['slug']),
                $report['orphans']
            ));
        }

        if (!$report['success']) {
            $io->error($report['error'] ?? 'Sync fehlgeschlagen.');

            return Command::FAILURE;
        }

        $io->success($dryRun ? 'Dry-Run abgeschlossen.' : 'Sync abgeschlossen.');

        return Command::SUCCESS;
    }
}
