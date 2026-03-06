<?php

namespace App\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final readonly class ExceptionListener
{
    public function __construct(
        private LoggerInterface $logger,
        private string $environment = 'prod',
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        $this->logger->error('Unhandled exception occurred.', [
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ]);

        $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR;
        $errorMessage = 'An internal server error occurred.';

        if ($exception instanceof HttpExceptionInterface) {
            $statusCode = $exception->getStatusCode();
            $errorMessage = $exception->getMessage();
        } elseif ($exception instanceof \JsonException) {
            $statusCode = Response::HTTP_BAD_REQUEST;
            $errorMessage = 'Invalid JSON payload.';
        } elseif ($exception instanceof \RuntimeException) {
            $statusCode = Response::HTTP_NOT_FOUND;
            $errorMessage = $exception->getMessage();
        }

        $responseData = [
            'error' => $errorMessage,
            'status' => $statusCode,
        ];

        if ($this->environment === 'dev') {
            $responseData['debug'] = [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ];
        }

        $response = new JsonResponse($responseData, $statusCode);
        $event->setResponse($response);
    }
}
