<?php defined('__GX__') or die('Access denied!');

class Request extends EObject {

	CONST VERSION = '3.0.0';
	public static $method = 'both';
	
	/**
	 * @return string HTTP HOST
	 */
	public static function host()
	{
		return $_SERVER['HTTP_HOST'];
	}

	/**
	 * @return string REMOTE IP
	 */
	public static function ip()
	{
		return $_SERVER['REMOTE_ADDR'];
	}

	/**
	 * @return bool tru if data Posted, false otherwise
	 */
	public static function isPost() {
		if (isset($_POST) && count($_POST)>0)
			return true;

		return false;
	}


	/**
	 * @return bool tru if data Requested, false otherwise
	 */
	public static function isRequest() {
		if (isset($_REQUEST) && !empty($_REQUEST))
			return true;

		return false;
	}

	/**
	 * @return string current URL
	 */
	public static function getUrl() {

		$URI = $_SERVER['REQUEST_URI'];
		$URI_SPLIT = explode("?", $URI);

		return URL_BASE != '/' ? str_replace( URL_BASE, '/', $URI_SPLIT[0] ) : $URI_SPLIT[0];
	}

	/**
	 * @return Array $_REQUEST object
	 */
	public static function getRequest($object = false) {
		if (!empty($_REQUEST) && !$object) {
			foreach ($_REQUEST AS $key => $value) {
				$r[$key] = $value;
			}
			return $r;
		} else if ($object) {
			$r = new stdClass();
			foreach ($_REQUEST AS $key => $value) {
				$r->$key = $value;
			}
		}
		return false;
	}

	/**
	 * @return Array $_POST object
	 */
	public static function getPost($object = false) {

		if (!empty($_POST) && !$object) {
			foreach ($_POST AS $key => $value) {
				$r[$key] = $value;
			}
			return $r;
		} else if ($object) {
			$r = new stdClass();
			foreach ($_POST AS $key => $value) {
				$r->$key = $value;
			}
		}

		return false;
	}

	/**
	 * @return Array $_GET object
	 */
	public static function getGet($object = false) {

		if (!empty($_GET) && !$object) {
			foreach ($_GET AS $key => $value) {
				$r[$key] = $value;
			}
			return $r;
		} else if ($object) {
			$r = new stdClass();
			foreach ($_GET AS $key => $value) {
				$r->$key = $value;
			}
		}

		return false;
	}

	public static function getVar($varName, $default = null, $type = null) {
		$method = strtolower(self::$method);
		$_r = !$method || $method == 'both' ? self::getRequest(false) : ($method == 'post' ? self::getPost(false) : self::getGet());
		
		if (!is_array($_r))
			return $default;
		
		if (array_key_exists($varName, $_r)) {
			if (!$type == null)	 {
				switch (strtolower($type)) {
					case 'string':
						return ((string)$_r[$varName]);
					break;
					case 'int':
						return (int)$_r[$varName];
					break;
					case 'double':
						return (double)$_r[$varName];
					break;
					case 'float':
						return (float)$_r[$varName];
					break;
					case 'bool':
						return (bool)$_r[$varName];
					break;
					case 'array':
					default: 
						foreach ($_r[$varName] as $key => $value) {
							$_r[$varName][$key] = ($value);
						}
						return $_r[$varName];
					break;
				}
			}
			if (!is_array($_r[$varName]))
				return $_r[$varName];
			else {
				foreach ($_r[$varName] as $key => $value) {
					$_r[$varName][$key] = ($value);
				}
				return $_r[$varName];
			}
		} else return $default;
	}

	public static function realIP() {
		if (isset($_SERVER)) {
			if (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
				$realip = $_SERVER["HTTP_X_FORWARDED_FOR"];
			}
			elseif (isset($_SERVER["HTTP_CLIENT_IP"])) {
				$realip = $_SERVER["HTTP_CLIENT_IP"];
			}
			else {
				$realip = $_SERVER["REMOTE_ADDR"];
			}
		} else {
			if ( getenv( 'HTTP_X_FORWARDED_FOR' ) ) {
				$realip = getenv( 'HTTP_X_FORWARDED_FOR' );
			}
			elseif ( getenv( 'HTTP_CLIENT_IP' ) ) {
				$realip = getenv( 'HTTP_CLIENT_IP' );
			}
			else {
				$realip = getenv( 'REMOTE_ADDR' );
			}
		}
		
		return $realip;

	}

	public static function isBOT($USER_AGENT) {
		$crawlers = array(
			'Google' => 'Google',
			'MSN' => 'msnbot',
			'Rambler' => 'Rambler',
			'Yahoo' => 'Yahoo',
			'AbachoBOT' => 'AbachoBOT',
			'accoona' => 'Accoona',
			'AcoiRobot' => 'AcoiRobot',
			'ASPSeek' => 'ASPSeek',
			'CrocCrawler' => 'CrocCrawler',
			'Dumbot' => 'Dumbot',
			'FAST-WebCrawler' => 'FAST-WebCrawler',
			'GeonaBot' => 'GeonaBot',
			'Gigabot' => 'Gigabot',
			'Lycos spider' => 'Lycos',
			'MSRBOT' => 'MSRBOT',
			'Altavista robot' => 'Scooter',
			'AltaVista robot' => 'Altavista',
			'ID-Search Bot' => 'IDBot',
			'eStyle Bot' => 'eStyle',
			'Scrubby robot' => 'Scrubby',
			'Facebook' => 'facebookexternalhit',
			);
		
		  // to get crawlers string used in function uncomment it
		  // it is better to save it in string than use implode every time
		  // global $crawlers
		$crawlers_agents = implode('|',$crawlers);
		if (strpos($crawlers_agents, $USER_AGENT) === false)
			return false;
		else {
			return TRUE;
		}
	}
}