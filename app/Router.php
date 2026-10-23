<?php
declare(strict_types=1);

final class Router
{
    private const ROUTES = [
        'index', 'login', 'register', 'home', 'control', 'queues',
        'about', 'faqs', 'post', 'overview', 'posts',
        'comments', 'restricted', 'reported',
        'notifications', 'profile', 'search',
        'mobile-feed', 'mobile-queues', 'mobile-reports', 'mobile-post', 'mobile-comments', 'mobile-users',
    ];

    public static function dispatch(?string $requestedRoute = null): void
    {
        $route = self::normalize($requestedRoute);
        (new PageController($route))->run();
    }

    private static function normalize(?string $requestedRoute): string
    {
        $route = trim((string)$requestedRoute);
        if ($route === '') {
            $route = 'index';
        }
        $route = basename(parse_url($route, PHP_URL_PATH) ?: $route);
        $route = preg_replace('/\.php$/i', '', $route) ?: 'index';
        return in_array($route, self::ROUTES, true) ? $route : 'not-found';
    }
}
