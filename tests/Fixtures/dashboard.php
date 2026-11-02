<?php

/**
 * A Dashboard for PHP's built-in server. It records each request it gets in
 * the file GLUE_TEST_RECORD names, and answers by the first segment of the
 * path: /ok/ accepts, /refuse/ refuses field by field, /broken/ is a proxy's
 * error page.
 */

$body = (string) file_get_contents('php://input');
parse_str($body, $form);

file_put_contents((string) getenv('GLUE_TEST_RECORD'), json_encode([
    'method'        => $_SERVER['REQUEST_METHOD'],
    'path'          => parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH),
    'authorization' => $_SERVER['HTTP_AUTHORIZATION'] ?? null,
    'accept'        => $_SERVER['HTTP_ACCEPT'] ?? null,
    'contentType'   => $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? null,
    'form'          => $form,
]));

switch (explode('/', trim((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'))[0]) {
    case 'ok':
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        break;

    case 'refuse':
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'errors' => ['version' => ['The version field is required.'], 'woodypim.hypernode.io' => ['This install is manual on the Dashboard.']]]);
        break;

    default:
        http_response_code(502);
        echo '<html><body>Bad Gateway</body></html>';
}
