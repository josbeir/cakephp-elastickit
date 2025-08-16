<?php
declare(strict_types=1);

namespace ElasticKit\Log;

use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Stringable;

class DebugKitLog extends AbstractLogger
{
    protected array $requests = [];

    /**
     * Constructor.
     */
    public function __construct(
        protected LoggerInterface $logger,
        protected string $connectionName,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        if (!isset($context['request'])) {
            return;
        }

        /** @var \Laminas\Diactoros\ServerRequest $request */
        $request = $context['request'];
        $body = null;
        if (in_array($request->getMethod(), ['POST', 'PUT', 'DELETE', 'HEAD', 'PATCH', 'GET'])) {
            $body = $request->getBody()->getContents();
            $body = $this->decodeBody($body);
        }

        $this->requests[] = [
            'message' => $message,
            'body' => $body,
            'context' => $context,
        ];
    }

    /**
     * Decode the JSON body of the request.
     *
     * @param string|null $body The JSON body to decode.
     * @return string|null The decoded body or null if decoding fails.
     */
    protected function decodeBody(?string $body): ?string
    {
        if (empty($body)) {
            return null;
        }

        $decoded = json_encode(json_decode($body), JSON_PRETTY_PRINT);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $body;
        }

        return $decoded ?: null;
    }

    /**
     * Get the requests logged for this connection.
     */
    public function requests(): array
    {
        return $this->requests;
    }
}
