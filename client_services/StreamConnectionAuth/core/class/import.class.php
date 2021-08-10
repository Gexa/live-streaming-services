<?php

defined('__GX__') or die('Access denied!');

class Import {

	public static function _class($classname, $path = '', $prefix = '') {

		if ($path == '' or $path == null)
			$path = APPLICATION_PATH . DS . 'class' . DS;

		$filename = strtolower($prefix . $classname) . '.class.php';
		$file_path = $path . $filename;
		$file_path_alt = $path . strtolower($prefix . $classname) . '.php';
		$alternative_path = CORE_CLASS . DS;

		if (file_exists($file_path)) {
			require_once($file_path);
		} else if (file_exists($file_path_alt)) {
			require_once($file_path_alt);
		} else if (file_exists($alternative_path . $filename)) {
			require_once($alternative_path . $filename);
		} else {

			var_dump(
				Array(
					'System message' => 'None of these files found:',
					$file_path,
					$file_path_alt,
					$alternative_path.$filename
					));

			die;

		}
	}

}

?>
