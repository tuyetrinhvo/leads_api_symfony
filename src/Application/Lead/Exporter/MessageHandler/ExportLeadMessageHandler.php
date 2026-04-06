<?php

declare(strict_types=1);

namespace App\Application\Lead\Exporter\MessageHandler;

use App\Application\Lead\Exporter\LeadExporter;
use App\Application\Lead\Exporter\Message\ExportLeadMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;


#[AsMessageHandler]
final readonly class ExportLeadMessageHandler
{
    public function __construct(
        private LeadExporter $leadExporter,
    ) {
    }

    public function __invoke(ExportLeadMessage $message): void
    {
        $this->leadExporter->exportOneById($message->leadId);
    }
}
