<?php
define('__GX__', 1);
require 'core/core.php';


error_reporting(E_ALL);
ini_set('display_errors' , 1);

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");


$name = isset($_REQUEST['name']) ? $_REQUEST['name'] : false;
if (!$name) {
    header("HTTP/1.0 404 Not Found");
    die('Access denied!');
}

$db = DB::getInstance();

$db->setQuery('SELECT streamer_id FROM stream_mapping WHERE streamkey LIKE BINARY '.$db->quote($name));
$result = (int)$db->loadResult();

if (!$result || $result == 0) {
    file_put_contents(__DIR__.DS.'tmp/x_disconnect_noresult.txt', var_export($_REQUEST, true));
    header("HTTP/1.0 404 Not Found");
} else {
    file_put_contents(__DIR__.DS.'tmp/x_disconnect_OK.txt', var_export($result, true));
    $db->setQuery('UPDATE stream_mapping SET `status` = 0 WHERE streamer_id = '.(int)$result);
    $db->query();
    echo 'OK';
}