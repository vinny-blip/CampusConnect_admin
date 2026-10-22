<?php
declare(strict_types=1);

final class Router
{
    private const ROUTES = [
        'index', 'login', 'register', 'home', 'newest', 'control', 'queues',
        'modmail', 'messages', 'designatmobbin', 'community', 'communities',
        'create-community', 'about', 'faqs', 'post', 'overview', 'posts',
        'comments', 'saved', 'restricted', 'reported', 'create',
        'notifications', 'profile', 'search',
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
