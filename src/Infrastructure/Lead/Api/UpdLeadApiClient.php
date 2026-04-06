<?php

declare(strict_types=1);

namespace App\Infrastructure\Lead\Api;

use App\Application\Lead\Exporter\Dto\ExportLeadDto;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class UpdLeadApiClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl,
        private string $apiKey,
        private string $exportPath = '/leads',
        private string $apiKeyHeader = 'X-API-Key',
        private float $timeout = 10.0,
    ) {
    }

    public function exportLead(ExportLeadDto $payload): void
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($this->exportPath, '/');

        $response = $this->httpClient->request('POST', $url, [
            'headers' => [
                $this->apiKeyHeader => $this->apiKey,
                'Accept' => 'application/json',
            ],
            'json' => $payload->toArray(),
            'timeout' => $this->timeout,
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode >= 200 && $statusCode < 300) {
            return;
        }

        $body = $response->getContent(false);

        throw new \RuntimeException(sprintf(
            'UPD export failed with HTTP %d. Response: %s',
            $statusCode,
            mb_substr($body, 0, 500)
        ));
    }
}
