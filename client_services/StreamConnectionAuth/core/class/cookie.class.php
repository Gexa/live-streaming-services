<?php defined('__GX__') or die('ACCESS DENIED!');

// Deprecated konstansok...
define('SESSION_LIFETIME', 0);
define('ONE_HOUR', time() + 60 * 60);
define('ONE_DAY', time() + 60 * 60 * 24);
define('ONE_WEEK', time() + 60 * 60 * 24 * 7);
define('ONE_MONTH', time() + 60 * 60 * 24 * 30);
define('SIX_MONTH', time() + 60 * 60 * 24 * 183);
define('HALF_YEAR', time() + 60 * 60 * 24 * 183);
define('ONE_YEAR', time() + 60 * 60 * 24 * 365);

define('COOKIE_SESSION_LIFETIME', 0);
define('COOKIE_ONE_HOUR', time() + 60 * 60);
define('COOKIE_ONE_DAY', time() + 60 * 60 * 24);
define('COOKIE_ONE_WEEK', time() + 60 * 60 * 24 * 7);
define('COOKIE_ONE_MONTH', time() + 60 * 60 * 24 * 30);
define('COOKIE_SIX_MONTH', time() + 60 * 60 * 24 * 183);
define('COOKIE_HALF_YEAR', time() + 60 * 60 * 24 * 183);
define('COOKIE_ONE_YEAR', time() + 60 * 60 * 24 * 365);

/**
 * Enter description here ...
 * @author Gexa
 *
 */
class Cookie {

	CONST VERSION = '3.0.0';
	
	/**
	 * Enter description here ...
	 * @param unknown_type $AName
	 * @param unknown_type $AValue
	 * @param unknown_type $AExpired
	 */
	public static function set($AName, $AValue, $AExpired, $admin = false) {
		$cookiePrefix = '';
		if (defined('_ADMIN_') || $admin === true)
			$cookiePrefix = 'admin';
		$_COOKIE[$cookiePrefix . '__' . $AName] = $AValue;
		Session::set($AName, $AValue, $admin);

		setcookie($cookiePrefix . '__' . $AName, $_COOKIE[$cookiePrefix . '__' . $AName], $AExpired, URL_BASE);
	}

	/**
	 * Enter description here ...
	 * @param unknown_type $AName
	 */
	public static function del($AName) {
		$cookiePrefix = '';
		if (defined('_ADMIN_'))
			$cookiePrefix = 'admin';
		unset($_COOKIE[$cookiePrefix . '__' . $AName]);

		Session::del($AName);
		setcookie($cookiePrefix . '__' . $AName, '', -ONE_YEAR, URL_BASE);
	}

	/**
	 * Enter description here ...
	 * @param unknown_type $AName
	 * @return NULL
	 */
	public static function getVar($AName) {

		$session = session_get_cookie_params();

		$cookiePrefix = '';

		if (defined('_ADMIN_'))
			$cookiePrefix = 'admin';

		if (Cookie::exists($AName)) {
			if (!isset($_COOKIE[$cookiePrefix . '__' . $AName])) {
				return null;
			} else
				return $_COOKIE[$cookiePrefix . '__' . $AName];
		} elseif (Session::exists($AName)) {
			return Session::getVar($AName);
		} else {
			return null;
		}
	}

	/**
	 * Enter description here ...
	 * @param unknown_type $AName
	 */
	public static function exists($AName) {
		$cookiePrefix = '';
		if (defined('_ADMIN_'))
			$cookiePrefix = 'admin';
		return isset($_COOKIE[$cookiePrefix . '__' . $AName]); // or isset($_SESSION[$cookiePrefix . '__' . $AName]);
	}

}
