<?php

/**
 * Router for the PHP built-in server used by CurlHttpClientTest.
 * Echoes back what it received so the test can assert the transport behavior.
 */

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($path === '/status/404') {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['message' => 'Not found']);

    return;
}

if ($path === '/slow') {
    usleep(3000000); // 3 s — longer than the client timeout used in the test

    return;
}

http_response_code(200);
header('Content-Type: application/json');
header('X-Echo-Server: 1');

$headers = [];
foreach ($_SERVER as $key => $value) {
    if (strpos($key, 'HTTP_') === 0) {
        $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = $value;
    }
}

echo json_encode([
    'method' => $_SERVER['REQUEST_METHOD'],
    'uri' => $_SERVER['REQUEST_URI'],
    'headers' => $headers,
    'body' => file_get_contents('php://input'),
    'post' => $_POST,
    'files' => array_map(static function (array $file): array {
        return ['name' => $file['name'], 'size' => $file['size']];
    }, $_FILES),
]);
