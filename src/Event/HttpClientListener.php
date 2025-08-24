<?php
declare(strict_types=1);

namespace ElasticKit\Event;

use Cake\Event\EventListenerInterface;
use Cake\Http\Client\ClientEvent;
use Elastic\Elasticsearch\Client;

/**
 * This works around an issue where the Content-Type
 * compatibility header is not set correctly when using the Cake HTTP client.
 *
 * @package ElasticKit
 */
class HttpClientListener implements EventListenerInterface
{
    /**
     * @inheritDoc
     */
    public function implementedEvents(): array
    {
        return [
            'HttpClient.beforeSend' => 'beforeSend',
        ];
    }

    /**
     * Handles the beforeSend event to modify the request headers.
     *
     * @param \Cake\Http\Client\ClientEvent $event The event instance.
     */
    public function beforeSend(ClientEvent $event): void
    {
        $request = $event->getRequest();
        if ($request->hasHeader('Accept') && !$request->hasHeader('Content-Type')) {
            $acceptHeader = $request->getHeaderLine('Accept');
            $pattern = str_replace('%s', '.+', preg_quote(Client::API_COMPATIBILITY_HEADER, '/'));
            // Match pattern like "application/vnd.elasticsearch+json; compatible-with=9"
            if (preg_match('/' . $pattern . '/', $acceptHeader, $match)) {
                $request = $request->withHeader('Content-Type', $acceptHeader);
                $event->setRequest($request);
            }
        }
    }
}
