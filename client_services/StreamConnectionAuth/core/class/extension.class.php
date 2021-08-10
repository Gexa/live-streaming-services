<?php defined('__GX__') or die('Access denied!');

class Extension {

	CONST VERSION = '3.0.0';
	
	private static $loaded = array();
	private static $loadedJS = array();

	/**
	 * Loads a PHP extension from 'extension' directory, and adds it to output
	 * @param string $extension_name
	 */
	public static function load($extension_name, $dir = '') {

		if (strlen($dir)<1)$dir = $extension_name;
		$path = BASE_PATH . DS . 'extension' . DS . $dir . DS . $extension_name . '.php';
		if (!in_array($path, self::$loaded)) {
			$load = true;
			self::$loaded[] = $path;
		} else {
			$load = false;
		}

		if ($load && is_file($path)) {
			unset($load);
			require $path;
		}
	}

	/**
	 * Loads a javascript extension from 'extension' directory , and adds it to output
	 *
	 * @param string $extension_name
	 * @param string $dir
	 */
	public static function loadJS($extension_name, $dir = '') {
		$path = BASE_PATH . DS . 'extension' . DS . (($dir != '') ? $dir : $extension_name) . DS . $extension_name . '.js';
		$url = URL_BASE . 'extension/' . (($dir != '') ? $dir : $extension_name) . '/' . $extension_name . '.js';
		if (!in_array($path, self::$loadedJS)) {
			$load = true;
			self::$loadedJS[] = $path;
		} else {
			$load = false;
		}
		if ($load != false && is_file($path)) {
			$tpl = App::getTpl();
			$tpl -> addJS($url, '', true);
		}
	}
}