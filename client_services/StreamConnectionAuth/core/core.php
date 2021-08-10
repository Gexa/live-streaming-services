<?php defined('__GX__') or die('Access denied!');

header('Content-Type:text/html; charset=UTF-8');

defined('DS') or define('DS', DIRECTORY_SEPARATOR);

defined('BASE_PATH') or define('BASE_PATH', dirname(dirname(__FILE__)));
defined('CORE') or define('CORE', BASE_PATH . DS . 'core');
defined('CORE_CLASS') or define('CORE_CLASS', CORE . DS . 'class');
defined('CORE_INTERFACE') or define('CORE_INTERFACE', CORE . DS . 'interface');
defined('CORE_HELPER') or define('CORE_HELPER', CORE . DS . 'helpers');
defined('EXTENSION') or define('EXTENSION', BASE_PATH . DS . 'extension');

defined('APPLICATION_PATH') or define('APPLICATION_PATH', BASE_PATH . DS . 'application');
defined('TEMPLATES') or define('TEMPLATES', BASE_PATH . DS . 'templates');
defined('STORAGE') or define('STORAGE', BASE_PATH . DS . 'upload');
defined('TEMP') or define('TEMP', BASE_PATH . DS . 'tmp');

defined('PATH_CONTROLLERS') or define('PATH_CONTROLLERS', APPLICATION_PATH . DS . 'controller');
defined('PATH_MODELS') or define('PATH_MODELS', APPLICATION_PATH . DS . 'model');
defined('PATH_VIEWS') or define('PATH_VIEWS', APPLICATION_PATH . DS . 'view');

$urlPath = dirname($_SERVER['SCRIPT_NAME']);

define('URL_PATH', $urlPath);

if (strlen($urlPath) <= 1) {
	$urlPath = '/';
} else {
	$urlPath .= '/';
}

session_start();

if (!defined('_AJAX_')) {
	define('URL_BASE', $urlPath);
} else {
	if (!defined('_IMAGER_')) {
		define('URL_BASE', $_SESSION['URL_BASE']);
	}
}

if (!getenv('TV') || getenv('TV') != 1) {
	require dirname(__FILE__).DS.'config.php';
} else {
	require dirname(__FILE__).DS.'config_tv.php';
	define('_TV_', 1);
}

$_SESSION['FILES_STORAGE'] = STORAGE;

if (!defined('_AJAX_') && !defined('_IMAGER_')) {

	$_SESSION['URL_BASE'] = URL_BASE;
	$_SESSION['FILES_STORAGE_URL'] = URL_BASE . 'upload';
	$_SESSION['BASE_PATH'] = BASE_PATH;
}

if (Easy::debug === 1) {
	@ini_set('display_errors', 1);
	error_reporting(E_ALL);
} else {
	@ini_set('display_errors', 0);
	error_reporting(0);
}

/** Class autoloader * */
function __autoload($className) {
	$className = strtolower($className);
	if (strpos($className, 'interface') !== FALSE) {
		require CORE_INTERFACE . DS . str_replace('interface', '', $className) . '.interface.php';
	} else if (!class_exists($className) && is_file(CORE_CLASS . DS . $className . '.class.php')) {
		require CORE_CLASS . DS . $className . '.class.php';
	} else {
		throw new Exception('Cannot find Class: ' . CORE_CLASS . DS . $className . '.class.php ... Exiting...');
	}
}
