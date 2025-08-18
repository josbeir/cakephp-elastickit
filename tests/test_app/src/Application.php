<?php
declare(strict_types=1);

namespace TestApp;

use Cake\Datasource\ConnectionManager;
use Cake\Http\BaseApplication;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\RouteBuilder;
use ElasticKit\Datasource\Connection;

class Application extends BaseApplication
{
    protected bool $connectionConfigured = false;

    public function bootstrap(): void
    {
        ConnectionManager::setConfig('elasticsearch', [
            'className' => Connection::class,
        ]);
    }

    public function routes(RouteBuilder $routes): void
    {
    }

    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        return $middlewareQueue;
    }
}
