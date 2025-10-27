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
function run_routes(array $routes, string $path, string $method, array $auth, $dbService, $response): void {
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
        $response->notFound(null, 'NOT_FOUND');
    }

    if (!isset($routes[$base])) {
        $response->notFound(null, 'NOT_FOUND');
    }

    $mapping = $routes[$base];
    if (!isset($mapping[$mode]) || !is_array($mapping[$mode])) {
        $response->notFound(null, 'NOT_FOUND');
    }

    $handler = $mapping[$mode][$method] ?? null;
    // handler must be a callable (closure or function name)
    if (!$handler || !is_callable($handler)) {
        $response->notFound(null, 'NOT_FOUND');
    }

    // read request body once and pass to handlers
    $body = $response->readJsonBody();

    $context = ['db' => $dbService, 'auth' => $auth];

    try {
        $result = call_user_func($handler, $pathVars, $body, $context);

        if (is_array($result) && array_key_exists('data', $result)) {
            $response->sendJson($result['data'], $result['status'] ?? 200, $result['headers'] ?? [], $result['outcome'] ?? 'ALLOW', $result['reason'] ?? 'OK', $result['key'] ?? $auth['key'] ?? null);
        } else {
            // handler didn't return a response structure
            $response->notFound($auth['key'] ?? null, 'NOT_FOUND');
        }
    } catch (StorageException $e) {
        $response->notFound(null, 'STORAGE_ERROR');
    } catch (\Exception $e) {
        // Map exception message to reason if provided, else generic NOT_FOUND
        $reason = $e->getMessage() ?: 'NOT_FOUND';
        $response->notFound($auth['key'] ?? null, $reason);
    }
}


