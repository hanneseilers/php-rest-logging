<?php

require_once __DIR__ . '/response.php';
require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/access.php';

// -------- Routes --------
// instantiate helpers / services (assumes $CONFIG exists in bootstrap)
$authorizer = new Authorizer($CONFIG ?? []);
$dbService  = Database::getInstance();
$response   = Response::getInstance();

// Authorize once for the current request and path. Authorizer will call
// Response::notFound() and exit on failure, so below we only run routes when
// authorization succeeded and returned an auth array.
try {
    $auth = $authorizer->authorize($METHOD, $PATH);
} catch (AuthorizationException $e) {
    // Convert authorization failure to the existing response/logging behaviour
    Response::getInstance()->notFound($e->key ?? null, $e->reason);
}

// Define route map. Base name -> modes (noParam/withParam) -> HTTP method -> handler (callable/closure)
$routes = [
    'items' => [
        'noParam' => [
            'GET' => function($pathVars, $body) use ($dbService, $auth) {
                $ids = $dbService->listIds();
                $items = [];
                foreach ($ids as $i) {
                    $it = $dbService->getItem($i);
                    if ($it !== null) $items[] = $it;
                }
                return ['data' => array_values($items), 'status' => 200, 'headers' => [], 'outcome' => 'ALLOW', 'reason' => 'OK', 'key' => $auth['key'] ?? null];
            },
            'POST' => function($pathVars, $body) use ($dbService, $auth) {            
                $item = $dbService->saveItem($body);
                return ['data' => $item, 'status' => 201, 'headers' => ['Location' => "/items/{$item['id']}"], 'outcome' => 'ALLOW', 'reason' => 'OK', 'key' => $auth['key'] ?? null];
            }
        ],
        'withParam' => [
            'GET' => function($pathVars, $body) use ($dbService, $auth) {
                $id = $pathVars['id'] ?? null;
                $item = $dbService->getItem($id);
                if (!$item) throw new \Exception('NOT_FOUND');
                return ['data' => $item, 'status' => 200, 'headers' => [], 'outcome' => 'ALLOW', 'reason' => 'OK', 'key' => $auth['key'] ?? null];
            },
            'PUT' => function($pathVars, $body) use ($dbService, $auth) {
                $id = $pathVars['id'] ?? null;
                if (!json_validate($body)) throw new \Exception('INPUT_INVALID');
                $item = $dbService->saveItem($body, $id);
                $created = ($item['createdAt'] === $item['lastUpdatedAt']);
                return ['data' => $item, 'status' => $created ? 201 : 200, 'headers' => $created ? ['Location' => "/items/$id"] : [], 'outcome' => 'ALLOW', 'reason' => 'OK', 'key' => $auth['key'] ?? null];
            }
        ]
    ]
];

require_once __DIR__ . '/routes-logic.php';

try {
    $result = run_routes($routes, $PATH, $METHOD, $response);
    if (is_array($result) && array_key_exists('data', $result)) {
        $response->sendJson($result['data'], $result['status'] ?? 200, $result['headers'] ?? [], $result['outcome'] ?? 'ALLOW', $result['reason'] ?? 'OK', $result['key'] ?? $auth['key'] ?? null);
    } else {
        $response->notFound($auth['key'] ?? null, 'NOT_FOUND');
    }
} catch (StorageException $e) {
    // storage errors map to STORAGE_ERROR
    $response->notFound($auth['key'] ?? null, 'STORAGE_ERROR');
} catch (InvalidInputException $e) {
    // Invalid input should produce a 400 Bad Request response
    $response->sendJson(['error' => 'Invalid input'], 400, [], 'DENY', 'INVALID_INPUT', $auth['key'] ?? null);
} catch (\Exception $e) {
    // propagate auth info for notFound
    $reason = $e->getMessage() ?: 'NOT_FOUND';
    $response->notFound($auth['key'] ?? null, $reason);
}