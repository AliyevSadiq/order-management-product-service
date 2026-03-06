<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class HealthCheckService
{
    private const TIMEOUT_SECONDS = 3;

    public function __construct(
        private Connection $connection,
        private HttpClientInterface $httpClient,
        private string $redisUrl,
        private string $elasticsearchHost,
        private string $kafkaBroker,
    ) {
    }

    /** @return array<string, array{status: string, response_time_ms: float, error?: string}> */
    public function check(): array
    {
        return [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'elasticsearch' => $this->checkElasticsearch(),
            'kafka' => $this->checkKafka(),
        ];
    }

    private function checkDatabase(): array
    {
        $start = microtime(true);
        try {
            $this->connection->executeQuery('SELECT 1');

            return [
                'status' => 'up',
                'response_time_ms' => round((microtime(true) - $start) * 1000, 2),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'down',
                'response_time_ms' => round((microtime(true) - $start) * 1000, 2),
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkRedis(): array
    {
        $start = microtime(true);
        try {
            $parsed = parse_url($this->redisUrl);
            $redis = new \Redis();
            $redis->connect(
                $parsed['host'] ?? 'redis',
                $parsed['port'] ?? 6379,
                self::TIMEOUT_SECONDS,
            );
            if (!empty($parsed['pass'])) {
                $redis->auth($parsed['pass']);
            }
            $redis->ping();
            $redis->close();

            return [
                'status' => 'up',
                'response_time_ms' => round((microtime(true) - $start) * 1000, 2),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'down',
                'response_time_ms' => round((microtime(true) - $start) * 1000, 2),
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkElasticsearch(): array
    {
        $start = microtime(true);
        try {
            $response = $this->httpClient->request('GET', $this->elasticsearchHost . '/_cluster/health', [
                'timeout' => self::TIMEOUT_SECONDS,
            ]);

            $data = $response->toArray();
            $clusterStatus = $data['status'] ?? 'unknown';

            return [
                'status' => $clusterStatus !== 'red' ? 'up' : 'degraded',
                'response_time_ms' => round((microtime(true) - $start) * 1000, 2),
                'cluster_status' => $clusterStatus,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'down',
                'response_time_ms' => round((microtime(true) - $start) * 1000, 2),
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkKafka(): array
    {
        $start = microtime(true);
        try {
            $parts = explode(':', $this->kafkaBroker);
            $host = $parts[0];
            $port = (int) ($parts[1] ?? 9092);

            $socket = @fsockopen($host, $port, $errno, $errstr, self::TIMEOUT_SECONDS);
            if ($socket === false) {
                return [
                    'status' => 'down',
                    'response_time_ms' => round((microtime(true) - $start) * 1000, 2),
                    'error' => "Connection failed: $errstr ($errno)",
                ];
            }
            fclose($socket);

            return [
                'status' => 'up',
                'response_time_ms' => round((microtime(true) - $start) * 1000, 2),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'down',
                'response_time_ms' => round((microtime(true) - $start) * 1000, 2),
                'error' => $e->getMessage(),
            ];
        }
    }
}
