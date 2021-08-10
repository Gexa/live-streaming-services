<?php
ob_start();

defined('ENTRYPOINT') or die('Access denied!');
global $API_ACTION;

error_reporting(0);
ini_set('display_errors' , 0);

foreach ($_REQUEST AS $k => $v) {
    $$k = trim(stripslashes(strip_tags(html_entity_decode($v))));
}

require __DIR__ . '/class/GCInstance.php';
require __DIR__ . '/class/DBMethods.php';

function handleRequest($API_ACTION, $id) {
    $gcInstanceHandler = new GCInstance("europe-west2-c");
    $gcInstanceHandler->setInstanceTemplate('stream');
    $dbMethods = new DBMethods();
    if (strlen($id) > 0) {
        $streamers = $dbMethods->getStreamers($id);
    }

    switch ($API_ACTION) {
        default:
        case 'listInstances':
            $serverResult = $gcInstanceHandler->list();
            $response = json_encode([
                'success' => 1,
                'servers' => $serverResult,
                'status' => $dbMethods->list($serverResult)
            ]);
            break;
        case 'createInstance':
            $serverResult = $gcInstanceHandler->create();
            $response = json_encode([
                'success' => 1,
                'server' => $serverResult,
                'status' => $dbMethods->list($gcInstanceHandler->list())
            ]);
            break;
        case 'getInstance':
            if (!isset($id)) {
                $response = json_encode([
                    'success' => 0,
                    'message' => 'No valid ID specified'
                ]);
            } else {
                $serverResult = $gcInstanceHandler->get($id);
                $response = json_encode([
                    'success' => 1,
                    'server' => $serverResult,
                    'status' => $dbMethods->get($serverResult),
                    'users' => $streamers
                ]);
            }
            break;
        case 'startInstance':
            if (!isset($id)) {
                $response = json_encode([
                    'success' => 0,
                    'message' => 'No valid ID specified'
                ]);
            } else {
                $serverResult = $gcInstanceHandler->start($id);
                $response = json_encode([
                    'success' => 1,
                    'server' => $serverResult,
                    'status' => $dbMethods->start($serverResult),
                    'users' => $streamers
                ]);
            }
            break;
        case 'resetInstance':
            if (!isset($id)) {
                $response = json_encode([
                    'success' => 0,
                    'message' => 'No valid ID specified'
                ]);
            } else {
                $serverResult = $gcInstanceHandler->reset($id);
                $response = json_encode([
                    'success' => 1,
                    'server' => $serverResult,
                    'status' => $dbMethods->get($serverResult),
                    'users' => $streamers
                ]);
            }
            break;
        case 'stopInstance':
            if (!isset($id)) {
                $response = json_encode([
                    'success' => 0,
                    'message' => 'No valid ID specified'
                ]);
            } else {
                $serverResult = $gcInstanceHandler->stop($id);
                $response = json_encode([
                    'success' => 1,
                    'server' => $serverResult,
                    'status' => $dbMethods->stop($serverResult),
                    'users' => $streamers
                ]);
            }
            break;
        case 'deleteInstance':
            if (!isset($id)) {
                $response = json_encode([
                    'success' => 0,
                    'message' => 'No valid ID specified'
                ]);
            } else {
                $serverResult = $gcInstanceHandler->delete($id);
                $response = json_encode([
                    'success' => 1,
                    'server' => $serverResult,
                    'status' => $dbMethods->delete($serverResult),
                    'users' => $streamers
                ]);
            }
            break;
        case 'getStream':
            $url = "http://{$_REQUEST['streamName']}.utopialive.hu:8080/?streamKey=".$_REQUEST['streamKey'];

            /* TODO: outsource credentials to config file or db */
            $username = '_UtopiaLiveStreamingEngine_';
            $password = 'Sm5f2m=f[]NJe~aY';

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/html'));
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_USERPWD, $username . ":" . $password);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            $return = curl_exec($ch);
            curl_close($ch);

            $response = json_encode(['success' => 1, 'url' => $return]);
            break;
    }

    return $response;
}

header('Content-Type: application/json; charset=UTF-8');
echo handleRequest($API_ACTION, isset($id) ? $id : null);