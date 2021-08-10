<?php defined('__GX__') or die('Access denied!');

class Router extends EObject {
	
	CONST VERSION = '3.0.0';
	
	private static $url = null;
	private static $state = Array();
	
	/**
	 * Check if URL exists in DB if not, throw 404 error AND
	 * IF there is no SEF, check request_uri for validation
	 */
	public static function isUrlExists() {
		
		$url = Request::getUrl();
		
		preg_match_all('/\/([0-9]{1,})\/$/si', $url, $matches);

		if (
			count($matches) == 2 && 
			isset($matches[1][count($matches[1])-1]) && 
			!is_null($matches[1][count($matches[1])-1]) && 
			substr($url, strlen($url)-1, 1) == '/'
			) 
		{
			//$url = str_replace($matches[0][count($matches[1])-1], '', $url);
			$mch = $matches[0][count($matches[1])-1];
			$url = preg_replace('#'. $mch . '$#si', '', $url);
			$navigate = true;
		} else $navigate = false;
		
		if ($url[strlen($url) - 1] === '/')
			$url = substr($url, 0, strlen($url) - 1);
		
		$_langSettings = self::setLanguage($url);
		$url = $_langSettings['url'];

		$db = DB::getInstance();
		$_q = 'SELECT * FROM route WHERE (language="*" OR language="'.Easy::$Language.'") AND url=' . $db -> quote($url);
		$db -> setQuery($_q);
		
		$exit = false;
		//// CHECK FOR NO SEF URL ////
		$list = $db -> loadAssocList();

		if ($url === '' || $url === '/') {
			return true;
		}

		if ((strpos($_SERVER['REQUEST_URI'], '?') > 0 or strpos($_SERVER['REQUEST_URI'], '&') > 0 or $url === '' or $url === '/') && count($list) === 0) {
			$exit = true;
		}


		if (!count($list) > 0)
			$exit = true;

		if (!strpos($url, '?') && !strpos($url, '&') && strlen($url) > 1 && !count($list))
			$exit = true;

		$dynamic = self::isDynamicURL();
		$exit = (!$dynamic && $exit) ? true : false;

		self::$url = $url;

		if ($navigate) {
			if (($dynamic && count($matches[1]) > 0) or (!$dynamic && count($matches[1]) > 1)) {
				self::$state = Array(
					'page' => ((int)$matches[1][count($matches[1])-1])  
				);
			} elseif (!$dynamic && count($matches[1] == 1)) {
				self::$state = Array(
					'page' => (int)$matches[1][0]
				);
			} 
		} else self::$state = Array();

		if ($exit != false)
			return false;

		return true;
	}

	public static function __static() {
		$layout = URL_BASE . 'templates/' . (!defined('_ADMIN_') ? Easy::template : Easy::template_admin);
		return $layout . (!Easy::$MobileView ? '' : '_mobile') . '/';
	}

	private static function isDynamicURL() {

		$db = DB::getInstance();

		//// CHECK FOR DYNAMIC URL ////
		$uri = explode('?', $_SERVER['REQUEST_URI'])[0];
		$uri = preg_replace('/\/([0-9]{1,})\/$/si', '', $uri);
		if (!Easy::languageControl) {
			$checkRegex = '/[\/a-zA-Z0-9\-\/]*?\/[a-zA-Z0-9\-]{3,}\/\d{1,}\/[a-zA-Z0-9\-]*$/si';
		} else {
			$checkRegex = '/[\/a-z\/]{2}?[\/a-zA-Z0-9\-\/]*?\/[a-zA-Z0-9\-]{3,}\/\d{1,}\/[a-zA-Z0-9\-]*$/si';
		}

		$m = array();
		preg_match($checkRegex, $uri, $m);

		if (count($m) != 1 || empty($m)) {
			return false;
		}
		
		if (Easy::languageControl != false) {
			$uri = preg_replace('/^[\/a-z\/]{3}/si', '', $uri);
		}

		$check = preg_replace('/\/\d{1,}\/[a-zA-Z0-9\-]*$/si', '/*', $uri);
		
		$dQ = 'SELECT * FROM route WHERE url LIKE '.$db->quote($check).' AND params LIKE "%?" AND (language="*" OR language=' . $db -> quote(Easy::$Language).')';
		$db -> setQuery($dQ);
		$dynamic = $db -> loadObject();
		
		if (!$dynamic) {
			return false;
		}

		$dURL = str_replace('/*', '/', $dynamic -> url);

		if (strpos($_SERVER['REQUEST_URI'], $dURL) > -1)
			return $dynamic;

		return false;
	}

	public static function setLanguage($url) {

		if (!strlen($url) > 1)
			return Array('is_lang' => false, 'url' => $url);
		
		if (defined('_AJAX_'))
			return Array('is_lang' => false, 'url' => $url);
		
		$langs = App::getLanguages();
		$uriSplit = explode('/', $url);

		$keys = array();
		$codes = array();
		foreach ($langs as $key => $lng) {
			$keys[$key] = $lng['key'];
		}
		$_li = array_intersect($keys, $uriSplit);

		$_lk = '';
		$is_lang = false;
		$lang_url = '';
		$language = '';
		if (count($_li) === 1) {
			
			$k = array_keys($_li);
			$_lk = $k[0];
			if ($_lk != '') {
				$url = preg_replace('#/' . $_li[$_lk].'#si', '', $url, 1);
				if ($url == '')
					$url = '/';
			}
			$is_lang = true;
			$lang_url = $_li[$_lk];
			$language = $_lk;
		}
		return Array('is_lang' => $is_lang, 'url' => $url, 'lang_url' => $lang_url, 'language' => $language);
	}

	/**
	 * Request setter function:
	 * modifies the global $_REQUEST object BY URL
	 */
	public static function setRequest() {

		if (self::isUrlExists()) {
			$url = self::$url;
		} else {
			$url = Request::getUrl();
		}
		
		$_langSettings = self::setLanguage($url);
		$url = $_langSettings['url'];

		if ($url != '' && $url != '/') {

			if ($url[strlen($url) - 1] == '/')
				$url = substr($url, 0, strlen($url) - 1);

			$is_dynamic = self::isDynamicURL();
			
			$db = DB::getInstance();
			$_q = 'SELECT * FROM route WHERE url=' . $db -> quote($url);
			$db -> setQuery($_q);

			if (!count($result = $db -> loadAssocList()) > 0 && !$is_dynamic) {
				App::show404ErrorPage();
				return;
			} elseif ($is_dynamic) {
				$result[0] = (array)$is_dynamic;
			}

			$_REQUEST['controller'] = $result[0]['controller'];
			$_REQUEST['action'] = $result[0]['action'];
			
			if (is_array(self::$state) && count(self::$state)>0)
				$_REQUEST['state'] = self::$state; 
			
			$_p = $result[0]['params'];
			$_ppieces = explode('&', $_p);

			if (!$is_dynamic) {
				if (count($_ppieces) > 1) {
					foreach ($_ppieces AS $key => $value) {
						$_pA = explode('=', $value);
						$_REQUEST[$_pA[0]] = $_pA[1];
					}
				} else {
					if ($_p != '') {
						$_pA = explode('=', $_p);
						$_REQUEST[$_pA[0]] = $_pA[1];
					}
				}
			} elseif (count($is_dynamic)) {

				$durl = str_replace('/*', '/', $is_dynamic -> url);
				$ruri = URL_BASE != '/' ? str_replace(URL_BASE, '/', $_SERVER['REQUEST_URI']) : $_SERVER['REQUEST_URI'];

				$from = strpos($ruri, $durl) + mb_strlen($durl);
				$search_in = urldecode(substr($ruri, $from, strlen($ruri)));
				
				$matches = Array();
				
				preg_match_all('/([0-9]{1,})/si', $search_in, $matches);
				$_REQUEST['id'] = $matches[1][0];
				
				
			}
		} elseif (empty($_GET) && ($url == '' or $url == '/')) {
			$_REQUEST['controller'] = 'default';
			$_REQUEST['action'] = 'default';
		} else {
			$_REQUEST['controller'] = (isset($_REQUEST['controller'])) ? $_REQUEST['controller'] : 'default';
			$_REQUEST['action'] = (isset($_REQUEST['action'])) ? $_REQUEST['action'] : 'default';
		}
	}

	/**
	 * creates SEF URL from string, 'controller->action', Array('id'=>1);
	 *
	 * @param mixed $route
	 * @param mixed $params
	 */
	public static function _($route, $params = null, $language = '') {

		$db = DB::getInstance();
		$p = '';
		if (!empty($params) && !is_string($params)) {
			$_p = Array();
			foreach ($params AS $key => $value) {
				$_p[] = $key . '=' . $value;
			}
			if (count($_p) > 1)
				$p = implode('&', $_p);
			else
				$p = $_p[0];
		} elseif (isset($params) && is_string($params)) {
			$p = $params;
		}

		$lngs = App::getLanguages();
		$lng = 
			((Easy::$Language != '' or $language) && Easy::languageControl) ? 
				('/' . (($language != '') ? 
					App::getLanguageCode($language) : 
					$lngs[Easy::$Language]['key']) . '/') : 
				'';

		$rArr = explode('->', $route);
		if (count($rArr) == 2) {
			$_q = '
				SELECT url FROM route 
				WHERE 
					controller=' . $db -> quote($rArr[0]) . ' AND 
					action=' . $db -> quote($rArr[1]) . ' AND (params= ' . (($p != '') ? $db -> quote($p) : ($db->quote('').' OR params IS NULL') ) . ')'. ((Easy::languageControl) ? ' AND (language=\'*\' OR language=' . $db -> quote(($language != '') ? $language : Easy::$Language) . ')' : '');
		} else if (count($rArr) == 3) {
			$_q = 'SELECT url FROM route WHERE 
				module=' . $db -> quote($rArr[0]) . ' AND 
				controller=' . $db -> quote($rArr[1]) . ' AND 
				action=' . $db -> quote($rArr[2]) . ' AND 
				(params= ' . (($p != '') ? $db -> quote($p) : ($db->quote('').' OR params IS NULL') ) . ')'. 
				((Easy::languageControl) ? '  AND (language=\'*\' OR language=' . $db -> quote(($language != '') ? $language : Easy::$Language) . ')' : '');
		}

		$db -> setQuery($_q);
		
		$result = $db -> loadResult();
		
		if (count($result) > 0 && Easy::SEF == 1) {
			$url = $result;
			return str_replace('//', '/', URL_BASE . $lng . $url);
		} else {
			$lng = ((Easy::languageControl) ? '&language=' . (($language != null) ? $language : Easy::$Language) : '');
			return URL_BASE . '?controller=' . $rArr[0] . '&action=' . $rArr[1] . ((isset($p) && $p != '') ? ('&' . $p) : '') . $lng;
		}
	}

	public static function _dynamic($route, $params = Array(), $language = '') {

		$lngs = App::getLanguages();
		$lng = ((Easy::$Language != '' or $language) && Easy::languageControl) ? 
					('/' . (($language != '') ? Easy::__lngCode($language) : 
					$lngs[Easy::$Language]['key']) . '/') : '';
		$rArr = explode('->', $route);

		if (!count($rArr))
			return false;

		$db = DB::getInstance();

		$q = 'SELECT url FROM route 
		  WHERE
		  controller = ' . $db -> quote($rArr[0]) . ' AND 
		  action = ' . $db -> quote($rArr[1]) . ' AND
		  params LIKE "%=?"
		  ' . ((Easy::languageControl) ? ' AND (language=\'*\' OR language=' . $db -> quote(($language != '') ? $language : Easy::$Language) . ')' : '');

		if (!$params['name'] or !$params['id'])
			return false;

		$db -> setQuery($q);
		$result = $db -> loadResult();

		if (count($result) > 0 && Easy::SEF == 1) {
			$url = str_replace('*', $params['id'] . '/' . Format::_alias($params['name']), $result);
			return str_replace('//', '/', URL_BASE . $lng . $url);
		} else {
			$p = 'id=' . $params['id'];
			$lng = ((Easy::languageControl) ? '&language=' . (($language != null) ? $language : Easy::$Language) : '');
			return URL_BASE . '?controller=' . $rArr[0] . '&action=' . $rArr[1] . '&' . $p . $lng;
		}

	}

	public static function URL($url) {

		if (!strlen($url) > 0)
			return 'javascript:void(0)';

		$db = DB::getInstance();

		$q = 'SELECT * FROM route WHERE url=' . $db -> quote($url);
		$db -> setQuery($q);
		if (!($result = $db -> loadObject())) {
			return $url;
		}

		$lngs = App::getLanguages();
		
		if (count($result) > 0 && Easy::SEF == 1) {
			$lng = (Easy::$Language != '' && Easy::languageControl) ? ('/' . $lngs[Easy::$Language]['key'] . '/') : '';
			return str_replace('//', '/', URL_BASE . $lng . $url);
		} elseif (count($result) > 0 && Easy::SEF != 1) {

			$lng = ((Easy::languageControl) ? '&language=' . Easy::$Language : '');
			return URL_BASE . '?controller=' . $result -> controller . '&action=' . $result -> action . '&' . $result -> params . $lng;
		}
	}
	
	public static function getUrl() {
		if (!is_null(self::$url))
			return self::$url;
		
		return false;
	}

	/** CREATES AN ADMIN ROUTE TO MODULE **/
	public static function admin($ctrl = null, $action = null, $params = array()) {
		if (!$ctrl)$ctrl = 'default';
		if (!$action)$action = 'default';
		$script = $_SERVER['PHP_SELF'];
		return $script . '?controller='.$ctrl.'&action='.$action . (count($params) > 0 ? ('&'.http_build_query($params)) : '');
	}
	
	/**
	 * Redirect function
	 *
	 * @param string $to (URL)
	 * @param bool $moved (is 301 redirect?)
	 */
	public static function redirect($to = '', $message = '', $moved = false) {

		if (mb_strlen(Format::makeSafe($message), 'UTF-8') > 0)
			Message::setMessage($message);

		if ($moved)
			header("HTTP/1.1 301 Moved Permanently");

		if ($to == 'refresh')
			header('Location: ' . $_SERVER['REQUEST_URI']);
		else
			header("Location: " . $to);

		exit ;

	}

}
?>