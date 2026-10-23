<?php

declare(strict_types=1);

$uri = is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : '/';
parse_str((string) parse_url($uri, PHP_URL_QUERY), $query);

if (isset($query['redirect'])) {
    header('Location: /elsewhere', true, 302);

    return;
}

http_response_code(is_numeric($query['status'] ?? null) ? (int) $query['status'] : 200);
header('Content-Type: application/json');

echo json_encode([
    'method' => $_SERVER['REQUEST_METHOD'],
    'uri' => $uri,
    'headers' => getallheaders(),
    'body' => file_get_contents('php://input'),
]);
