<?php

declare(strict_types=1);

namespace App\Application\Lead\Exporter\Message;

/**
 * Asynchronous message requesting export of a single lead.
 */
final readonly class ExportLeadMessage
{
    public function __construct(
        public int $leadId,
    ) {
    }
}
