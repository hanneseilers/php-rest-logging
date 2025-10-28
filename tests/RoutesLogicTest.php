<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../routes-logic.php';

class RoutesLogicTest extends TestCase {

    public function testRunRoutesNoParamCallsHandler(): void {
        $called = [];
        $routes = [
            'items' => [
                'noParam' => ['GET' => function($vars, $body) use (&$called) {
                    $called = ['vars' => $vars, 'body' => $body];
                    return ['data' => ['list' => []]];
                }]
            ]
        ];

        $response = new class {
            public function readJsonBody() { return null; }
        };

        $res = run_routes($routes, '/items', 'GET', $response);
        $this->assertIsArray($res);
        $this->assertArrayHasKey('data', $res);
        $this->assertSame([], $res['data']['list']);
        $this->assertSame([], $called['vars']);
    }

    public function testRunRoutesWithParamCallsHandler(): void {
        $captured = [];
        $routes = [
            'widgets' => [
                'withParam' => ['GET' => function($vars, $body) use (&$captured) {
                    $captured = ['vars' => $vars, 'body' => $body];
                    return ['data' => ['item' => ['id' => $vars['id']]]];
                }]
            ]
        ];

        $response = new class {
            public function readJsonBody() { return null; }
        };

        $res = run_routes($routes, '/widgets/42', 'GET', $response);
        $this->assertSame(42, $captured['vars']['id']);
        $this->assertSame(42, $res['data']['item']['id']);
    }

    public function testHandlerReturnsInvalidValueThrows(): void {
        $this->expectException(Exception::class);
        $routes = [
            'things' => [
                'noParam' => ['GET' => function($vars, $body) { return 'not an array'; }]
            ]
        ];
    $response = new class { public function readJsonBody() { return null; } };
        run_routes($routes, '/things', 'GET', $response);
    }

    public function testUnknownRouteThrows(): void {
        $this->expectException(Exception::class);
        $routes = [];
    $response = new class { public function readJsonBody() { return null; } };
        run_routes($routes, '/missing', 'GET', $response);
    }

    public function testNonCallableHandlerThrows(): void {
        $this->expectException(Exception::class);
        $routes = [
            'nope' => [
                'noParam' => ['GET' => 'not_a_function']
            ]
        ];
    $response = new class { public function readJsonBody() { return null; } };
        run_routes($routes, '/nope', 'GET', $response);
    }

    public function testHandlerExceptionPropagates(): void {
        $this->expectException(Exception::class);
        $routes = [
            'boom' => [
                'noParam' => ['GET' => function() { throw new \Exception('boom'); }]
            ]
        ];
    $response = new class { public function readJsonBody() { return null; } };
        run_routes($routes, '/boom', 'GET', $response);
    }

    public function testPathWithTooManySegmentsThrows(): void {
        $this->expectException(Exception::class);
        $routes = [ 'a' => [ 'noParam' => ['GET' => function() { return ['data'=>[]]; } ] ] ];
    $response = new class { public function readJsonBody() { return null; } };
        run_routes($routes, '/a/b/c', 'GET', $response);
    }
}
