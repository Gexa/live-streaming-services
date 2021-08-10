<?php defined('__GX__') or die('Access denied!'); 

/**
 * Enter description here ...
 * @author Gexa
 *
 */
class Session {

	CONST VERSION = '3.0.0';
	
	/**
	 * Enter description here ...
	 * @param unknown_type $AName
	 * @param unknown_type $AValue
	 */
	public static function set($AName, $AValue, $admin = false) {
		$sessionPrefix = '';
		if (defined('_ADMIN_') || $admin === true)
			$sessionPrefix = 'admin';
		$_SESSION[$sessionPrefix . '__' . $AName] = $AValue;
	}

	/**
	 * Enter description here ...
	 * @param unknown_type $AName
	 */
	public static function del($AName) {
		$sessionPrefix = '';
		if (defined('_ADMIN_'))
			$sessionPrefix = 'admin';
		
		// destroy session variable
		$_SESSION[$sessionPrefix . '__' . $AName] = null;
		unset($_SESSION[$sessionPrefix . '__' . $AName]);
	}

	/**
	 * Enter description here ...
	 * @param unknown_type $AName
	 * @return NULL
	 */
	public static function getVar($AName) {

		$session = $_SESSION;

		$sessionPrefix = '';

		if (defined('_ADMIN_'))
			$sessionPrefix = 'admin';

		if (Session::exists($AName)) {
			if (!isset($_SESSION[$sessionPrefix . '__' . $AName])) {
				return null;
			} else
				return $_SESSION[$sessionPrefix . '__' . $AName];
		} else {
			return null;
		}
	}

	/**
	 * Enter description here ...
	 * @param unknown_type $AName
	 */
	public static function exists($AName) {
		$sessionPrefix = '';
		if (defined('_ADMIN_'))
			$sessionPrefix = 'admin';
		return isset($_SESSION[$sessionPrefix . '__' . $AName]);
	}

}
