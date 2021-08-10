<?php
define('ENTRYPOINT', 1);
define('STREAM_API_BASE', '/API');

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');

if (strpos($_SERVER['REQUEST_URI'], STREAM_API_BASE) === FALSE) {
    die(json_encode(['success' => 0, 'message' => 'Access denied!']));
}

global $API_ACTION;

$API_ACTION = trim(str_replace(substr($_SERVER['REQUEST_URI'], 0, strpos($_SERVER['REQUEST_URI'], STREAM_API_BASE)) . STREAM_API_BASE, '', explode('?', $_SERVER['REQUEST_URI'])[0]), '/');

$valid_actions = [
    'listInstances' => 'GET',
    'getInstance' => 'GET',
    'getStreamers' => 'GET',
    'getStream' => 'GET',
    'createInstance' => 'POST',
    'startInstance' => 'POST',
    'resetInstance' => 'POST',
    'stopInstance' => 'POST',
    'deleteInstance' => 'POST'
];

$METHOD = $_SERVER['REQUEST_METHOD'];

if (!in_array($API_ACTION, array_keys($valid_actions))) {
    die(json_encode(['success' => 0, 'message' => 'Invalid API request']));
}

if (in_array($API_ACTION, array_keys($valid_actions)) && $METHOD != $valid_actions[$API_ACTION]) {
    die(json_encode(['success' => 0, 'message' => "Invalid Method for {$API_ACTION}"]));
}


require __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'handler.php';
