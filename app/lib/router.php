<?php
declare(strict_types=1);

/**
 * Tiny router. Patterns use {name} for integer ids, e.g. '/dm/stores/{id}'.
 * Handlers receive the captured ids as int arguments, in order.
 */
function dispatch(array $routes, string $method, string $path): void
{
    $path = '/' . trim($path, '/');
    $allowed = [];

    foreach ($routes as [$routeMethod, $pattern, $handler]) {
        $regex = '#^' . preg_replace('#\{\w+\}#', '(\d+)', $pattern) . '$#';
        if (!preg_match($regex, $path, $m)) {
            continue;
        }
        if ($routeMethod !== $method) {
            $allowed[] = $routeMethod;
            continue;
        }
        if ($method === 'POST') {
            verify_csrf();
        }
        $args = array_map('intval', array_slice($m, 1));
        $handler(...$args);
        return;
    }

    if ($allowed) {
        abort(405, 'That action is not allowed here.');
    }
    abort(404, 'There is nothing on this shelf.');
}
