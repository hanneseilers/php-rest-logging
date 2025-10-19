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

if ($PATH === '/items') {
    if ($METHOD === 'GET') {
        try {
            $db = $dbService->load();
        } catch (StorageException $e) {
            Response::getInstance()->notFound(null, 'STORAGE_ERROR');
        }
        $response->sendJson(array_values($db['items']), 200, [], 'ALLOW', 'OK', $auth['key']);
    }
    if ($METHOD === 'POST') {
        $payload = $response->readJsonBody();
        $name = isset($payload['name']) ? trim((string)$payload['name']) : '';
        if ($name === '') $response->notFound($auth['key'], 'INPUT_INVALID');
        try {
            $db = $dbService->load();
        } catch (StorageException $e) {
            Response::getInstance()->notFound(null, 'STORAGE_ERROR');
        }
        $id = $db['nextId']++;
        $item = ['id' => $id, 'name' => $name, 'createdAt' => gmdate('c')];
        $db['items'][(string)$id] = $item;
        try {
            $dbService->save($db);
        } catch (StorageException $e) {
            Response::getInstance()->notFound(null, 'STORAGE_ERROR');
        }
        $response->sendJson($item, 201, ['Location' => "/items/$id"], 'ALLOW', 'OK', $auth['key']);
    }
    $response->notFound(null, 'NOT_FOUND');
}

if (preg_match('#^/items/(\d+)$#', $PATH, $m)) {
    $id = (int)$m[1];
    if ($METHOD === 'GET') {
        try {
            $db = $dbService->load();
        } catch (StorageException $e) {
            Response::getInstance()->notFound(null, 'STORAGE_ERROR');
        }
        $item = $db['items'][(string)$id] ?? null;
        if (!$item) $response->notFound($auth['key'], 'NOT_FOUND');
        $response->sendJson($item, 200, [], 'ALLOW', 'OK', $auth['key']);
    }
    if ($METHOD === 'PUT') {
        $payload = $response->readJsonBody();
        $name = isset($payload['name']) ? trim((string)$payload['name']) : '';
        if ($name === '') $response->notFound($auth['key'], 'INPUT_INVALID');
        try {
            $db = $dbService->load();
        } catch (StorageException $e) {
            Response::getInstance()->notFound(null, 'STORAGE_ERROR');
        }
        $exists = isset($db['items'][(string)$id]);
        $item = [
            'id'        => $id,
            'name'      => $name,
            'updatedAt' => gmdate('c'),
            'createdAt' => $exists ? ($db['items'][(string)$id]['createdAt'] ?? gmdate('c')) : gmdate('c')
        ];
        $db['items'][(string)$id] = $item;
        try {
            $dbService->save($db);
        } catch (StorageException $e) {
            Response::getInstance()->notFound(null, 'STORAGE_ERROR');
        }
        $response->sendJson($item, $exists ? 200 : 201, $exists ? [] : ['Location' => "/items/$id"], 'ALLOW', 'OK', $auth['key']);
    }
    $response->notFound(null, 'NOT_FOUND');
}

$response->notFound(null, 'NOT_FOUND');