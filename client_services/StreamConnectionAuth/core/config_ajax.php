<?php
@ini_set('max_execution_time',600);
@ini_set('ignore_user_abort',1);

require(dirname(__FILE__).DIRECTORY_SEPARATOR.'core.php');

$GLOBALS['ajax'] = true;
define( 'NO_RENDER', 1 );

define('PATH_GATEWAY', dirname(__FILE__));
define('PATH_BASE', dirname(__FILE__) );

$app = new App();
$app->init();

if (!$_SESSION or empty($_SESSION)) {
	session_start();
}

if (!isset($_SESSION['_em3_gw_token']) && !isset($_REQUEST['token'])) {
	die('Active session could not be found!');
}