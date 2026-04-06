<?php

declare(strict_types=1);

namespace App\Command;

use App\Application\Lead\Exporter\LeadExporter;
use App\Application\Lead\Exporter\Message\ExportLeadMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:lead:export',
    description: 'Dispatch pending leads for asynchronous export.',
)]
final class ExportLeadsCommand extends Command
{
    public function __construct(
        private readonly LeadExporter $leadExporter,
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum number of leads to export.', '1000')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Flush and clear interval.', '200')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be exported without API calls nor DB changes.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $limit = max(1, (int) $input->getOption('limit'));
        $batchSize = max(1, (int) $input->getOption('batch-size'));
        $dryRun = (bool) $input->getOption('dry-run');

        if ($dryRun) {
            $result = $this->leadExporter->preview($limit);

            if (0 === $result['selected']) {
                $io->success('No leads to export.');

                return Command::SUCCESS;
            }

            $io->note(sprintf('Preparing export of %d lead(s).', $result['selected']));
            $io->success(sprintf(
                'Dry-run export complete. Leads selected: %d. No data was written.',
                $result['selected']
            ));

            return Command::SUCCESS;
        }

        $leadIds = $this->leadExporter->findExportableLeadIds($limit);

        if ([] === $leadIds) {
            $io->success('No leads to export.');

            return Command::SUCCESS;
        }

        $io->note(sprintf('Dispatching %d lead(s) to async export queue.', count($leadIds)));

        $dispatched = 0;

        foreach ($leadIds as $leadId) {
            $this->messageBus->dispatch(new ExportLeadMessage($leadId));
            ++$dispatched;

            if (0 === ($dispatched % $batchSize)) {
                $io->writeln(sprintf('Dispatched %d/%d...', $dispatched, count($leadIds)));
            }
        }

        $io->success(sprintf('Dispatched %d lead(s). Run "php bin/console messenger:consume async -vv" to process queue.', $dispatched));

        return Command::SUCCESS;
    }
}
