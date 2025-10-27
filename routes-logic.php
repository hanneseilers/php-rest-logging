<?php
require_once __DIR__ . '/response.php';

/**
 * Dispatcher: given the routes dictionary, the current path and method,
 * call the matching handler and send the response (or map errors to notFound).
 *
 * routes format example:
 * [
 *   'items' => [
 *     'noParam' => ['GET' => 'items_list', 'POST' => 'items_create'],
 *     'withParam' => ['GET' => 'items_get', 'PUT' => 'items_put']
 *   ]
 * ]
 */
function run_routes(array $routes, string $path, string $method, $response): array {
    // determine base and optional id param
    $pathVars = [];
    if (preg_match('#^/([^/]+)/(\d+)$#', $path, $m)) {
        $base = $m[1];
        $pathVars['id'] = (int)$m[2];
        $mode = 'withParam';
    } elseif (preg_match('#^/([^/]+)$#', $path, $m)) {
        $base = $m[1];
        $mode = 'noParam';
    } else {
        throw new \Exception('NOT_FOUND');
    }

    if (!isset($routes[$base])) {
        throw new \Exception('NOT_FOUND');
    }

    $mapping = $routes[$base];
    if (!isset($mapping[$mode]) || !is_array($mapping[$mode])) {
        throw new \Exception('NOT_FOUND');
    }

    $handler = $mapping[$mode][$method] ?? null;
    // handler must be a callable (closure or function name)
    if (!$handler || !is_callable($handler)) {
        throw new \Exception('NOT_FOUND');
    }

    // read request body once and pass to handlers
    $body = $response->readJsonBody();

    $result = call_user_func($handler, $pathVars, $body);

    if (is_array($result) && array_key_exists('data', $result)) {
        return $result;
    }
    // If handler returned something unexpected, signal not found to caller
    throw new \Exception('NOT_FOUND');
}


