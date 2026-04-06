<?php

declare(strict_types=1);

namespace App\Command;

use App\Application\Lead\Anonymizer\LeadAnonymizer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:lead:anonymize-old-data',
    description: 'Anonymize lead personal data older than N years (default: 2).',
)]
final class AnonymizeOldLeadsCommand extends Command
{
    public function __construct(
        private readonly LeadAnonymizer $leadAnonymizer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('years', null, InputOption::VALUE_REQUIRED, 'Anonymize leads older than this number of years.', '2')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Flush and clear interval.', '200')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be anonymized without writing changes.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $years = max(1, (int) $input->getOption('years'));
        $batchSize = max(1, (int) $input->getOption('batch-size'));
        $dryRun = (bool) $input->getOption('dry-run');
        $cutoff = (new \DateTimeImmutable())->modify(sprintf('-%d years', $years));

        $io->note(sprintf(
            'Scanning leads with createdAt <= %s (older than %d year%s).',
            $cutoff->format(\DateTimeInterface::ATOM),
            $years,
            $years > 1 ? 's' : ''
        ));

        if ($dryRun) {
            $result = $this->leadAnonymizer->previewOlderThanYears($years);

            $io->success(sprintf(
                'Dry-run completed. Processed %d leads, %d would be anonymized.',
                $result['processed'],
                $result['anonymizable']
            ));

            return Command::SUCCESS;
        }

        $result = $this->leadAnonymizer->anonymizeOlderThanYears($years, $batchSize);

        $io->success(sprintf(
            'Anonymization completed. Processed %d leads, anonymized %d.',
            $result['processed'],
            $result['anonymized']
        ));

        return Command::SUCCESS;
    }
}
