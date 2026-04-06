<?php

declare(strict_types=1);

namespace App\Domain\Lead;

enum LeadStatus: string
{
    case NEW = 'new';
    case VALIDATED = 'validated';
    case EXPORTED = 'exported';
    case DELETED = 'deleted';
}
