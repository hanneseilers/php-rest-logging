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

// Define route map. Base name -> modes (noParam/withParam) -> HTTP method -> handler function name
$routes = [
    'items' => [
        'noParam' => [
            'GET' => function($pathVars, $body, $context) use ($dbService, $response, $auth) {
                $ids = $dbService->listIds();
                $items = [];
                foreach ($ids as $i) {
                    $it = $dbService->getItem($i);
                    if ($it !== null) $items[] = $it;
                }
                return ['data' => array_values($items), 'status' => 200, 'headers' => [], 'outcome' => 'ALLOW', 'reason' => 'OK', 'key' => $auth['key'] ?? null];
            },

            'POST' => function($pathVars, $body, $context) use ($dbService, $response, $auth) {
                $name = isset($body['name']) ? trim((string)$body['name']) : '';
                if ($name === '') throw new \Exception('INPUT_INVALID');
                $item = $dbService->saveItem(['name' => $name]);
                return ['data' => $item, 'status' => 201, 'headers' => ['Location' => "/items/{$item['id']}"], 'outcome' => 'ALLOW', 'reason' => 'OK', 'key' => $auth['key'] ?? null];
            }
        ],

        'withParam' => [
            'GET' => function($pathVars, $body, $context) use ($dbService, $response, $auth) {
                $id = $pathVars['id'] ?? null;
                $item = $dbService->getItem($id);
                if (!$item) throw new \Exception('NOT_FOUND');
                return ['data' => $item, 'status' => 200, 'headers' => [], 'outcome' => 'ALLOW', 'reason' => 'OK', 'key' => $auth['key'] ?? null];
            },
            
            'PUT' => function($pathVars, $body, $context) use ($dbService, $response, $auth) {
                $id = $pathVars['id'] ?? null;
                $name = isset($body['name']) ? trim((string)$body['name']) : '';
                if ($name === '') throw new \Exception('INPUT_INVALID');
                $item = $dbService->saveItem(['name' => $name], $id);
                $created = ($item['createdAt'] === $item['lastUpdatedAt']);
                return ['data' => $item, 'status' => $created ? 201 : 200, 'headers' => $created ? ['Location' => "/items/$id"] : [], 'outcome' => 'ALLOW', 'reason' => 'OK', 'key' => $auth['key'] ?? null];
            }
        ]
    ]
];

require_once __DIR__ . '/routes-logic.php';

run_routes($routes, $PATH, $METHOD, $auth, $dbService, $response);