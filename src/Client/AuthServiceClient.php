<?php

namespace App\Client;

use App\Contract\AuthTokenValidatorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Psr\Log\LoggerInterface;

final readonly class AuthServiceClient implements AuthTokenValidatorInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private string $authServiceUrl,
    ) {}

    public function validateToken(string $token): ?array
    {
        try {
            $response = $this->httpClient->request('GET', $this->authServiceUrl . '/api/auth/profile', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                ],
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode !== 200) {
                $this->logger->warning('Auth service returned non-200 status', [
                    'status_code' => $statusCode,
                ]);
                return null;
            }

            $data = $response->toArray();
            $user = $data['user'] ?? $data;

            return [
                'userId' => $user['id'] ?? $user['userId'] ?? '',
                'email' => $user['email'] ?? '',
                'first_name' => $user['first_name'] ?? '',
                'last_name' => $user['last_name'] ?? '',
            ];
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Auth service connection failed', [
                'error' => $e->getMessage(),
            ]);
            return null;
        } catch (HttpExceptionInterface $e) {
            $this->logger->error('Auth service HTTP error', [
                'error' => $e->getMessage(),
                'status_code' => $e->getResponse()->getStatusCode(),
            ]);
            return null;
        }
    }
}
