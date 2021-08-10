<?php defined('__GX__') or die('Access denied!');

class App implements AppInterface {

	CONST VERSION = '3.0.0';

	private static $ctrl = 'default';
	private static $action = 'default';
	private static $tpl = null;
	private static $meta = null;
	private static $title = Easy::ProjectName;

	public function init() {
		
		if (Request::isPost() && Request::getVar('accept18', 0) == 1) {
			Session::set('accept18', 1);
			Router::redirect('refresh');
		}

		setlocale(LC_ALL, Easy::$Language.'.'.Easy::charset);
		self::initLanguage();

		if ((!defined('_ADMIN_') && !defined('_AJAX_')) || !Easy::SEF)Router::setRequest();
		App::$ctrl = Request::getVar('controller');
		App::$action = Request::getVar('action');
		
		$Theme = Cookie::getVar('easyTheme');
		if (Easy::$Theme != $Theme) {
			Easy::$Theme = $Theme;
		}

		if (!Easy::$Theme) {
			Easy::$Theme = 'default';
		}

		if (Easy::isMobileEnabled === true) {
			$is_mobile = App::isMobile() && !App::isTablet();
			
			Easy::$MobileView = $is_mobile;
			if (Easy::$MobileView === true && !defined('_ADMIN_') && Easy::loadMobileThemes) {
				Easy::$Theme = Easy::$Theme . '_mobile';
			}
		}
		if (Request::getVar('logout_user', null) != null && Request::isPost()) {
			App::getUser() -> logoutUser(Request::getVar('redirectTo', ''), Request::getVar('redirectMessage', ''));
			if (Request::getVar('redirectTo', '') == '') {
				Router::redirect('refresh', Request::getVar('redirectMessage', ''));
			}
		}

		Easy::$_settings = self::_settings(1);
	}

	public static function isMobile() {
		$Detect = new MobileDetect();
		return $Detect->isMobile() === TRUE;
	}

	public static function isTablet() {
		$Detect = new MobileDetect();
		return $Detect->isTablet() === TRUE;
	}

	public function initCaching() {
		Extension::load('phpfastcache');
		$c = new phpFastCache('files');
		phpFastCache::$storage = 'files';
		phpFastCache::$path = TEMP . DS . 'cache' . DS;
		return $c;
	}

	private static function initLanguage() {
		$url = explode('?', $_SERVER['REQUEST_URI']);
		$url = $url[0];
		$langs = App::getLanguages();
		$uriSplit = explode('/', $url);

		$keys = array();
		$codes = array();
		foreach ($langs as $key => $lng) {
			$keys[$key] = $lng['key'];
		}
		$_li = array_intersect($keys, $uriSplit);
		if (!empty($_li) && Easy::languageControl) {
			$lKey = array_keys($_li);
			Easy::$Language = $lKey[0];
		} else {
			Easy::$Language = Easy::defaultLanguage;
		}
	}
	
	public static function getAction() {
		return self::$action;
	}

	public static function getCtrl() {
		return self::$ctrl;
	}

	public static function getTpl() {
		if (!self::$tpl) {
			return Template::getInstance();
		}
		return self::$tpl;
	}

	public static function setTitle($title = '') {
		self::$title = $title;
	}

	public static function getTitle() {
		return self::$title;
	}

	public function render() {
		
		$urlExists = Router::isUrlExists();
		if (!$urlExists && !defined('_ADMIN_')) {
			App::show404ErrorPage();
		}

		if (!defined('_ADMIN_')) {
			$tplDir = TEMPLATES . DS . Easy::template;
		} else {
			$tplDir = TEMPLATES . DS . Easy::template_admin;
		}
		
		App::$tpl = App::getTpl();
		if (is_file($tplDir . DS . 'tpl.settings.php')) {
			require $tplDir . DS . 'tpl.settings.php';
		}

		$_r = Request::getRequest(false);
		if (isset($_r['controller']) && $_r['controller'] !== '' && $_r['controller'] !== 'default') {
			App::$ctrl = $_r['controller'];
		} elseif (!isset($_r['controller']) or $_r['controller'] == 'default') {
			App::$ctrl = 'default';
		}

		if (isset($_r['action']) && $_r['action'] !== '') {
			App::$action = $_r['action'];
		} else {
			App::$action = 'default';
		}

		if (!defined('_ADMIN_')) {
			$ctrlFile = PATH_CONTROLLERS . DS . App::$ctrl . '.php';
		} else {
			$ctrlFile = PATH_CONTROLLERS . DS . 'admin' . DS . App::$ctrl . '.php';
		}

		if (!is_file($ctrlFile)) {
			App::show404ErrorPage('Controller file doesn\'t exist: ' . $ctrlFile);
		}

		require $ctrlFile;

		ob_start();
		$ctrlClass = ucfirst(App::$ctrl) . 'Controller';
		$actionName = 'Action_' . strtolower(App::$action);
		
		$ctrl = new $ctrlClass();

		if (Session::getVar('accept18') == 1 || defined('_ADMIN_') || defined('_TV_')) {
			if (method_exists($ctrl, $actionName)) {
				// caching 
				if ($ctrl->caching() != false) {
					$cache = new phpFastCache('files');
					
					$keyword_ctrl = md5($ctrlClass . $actionName . Easy::$Language . (Easy::$MobileView ? '_mobile' : ''));
					$content = $cache->get($keyword_ctrl);
					if (is_null($content)) {
						ob_start();
						$ctrl -> $actionName();
						$content = ob_get_contents();
						ob_get_clean();
						$cache->set($keyword_ctrl, $content, Easy::cache_time);
					} else {
						ob_start();
						$ctrl -> $actionName();
						$content = ob_get_contents();
						ob_get_clean();
					}
				} else {
					ob_start();
					$ctrl -> $actionName();
					$content = ob_get_contents();
					ob_get_clean();
				}

			} else {
				App::show404ErrorPage(str_replace('_', ' ', $actionName) . ' of <strong>' . App::$ctrl . '</strong> controller doesn\'t exist!');
			}
		} else $content = '';

		$TPL = $tplDir . DS . (!defined('_ADMIN_')?Easy::template:Easy::template_admin) . '.tpl';
		ob_start();
			include ($TPL);
			$html = ob_get_contents();
		ob_get_clean();

		$message = Message::getMessage();
		$html = str_replace(
			Array('{{TITLE}}', '{{META}}', '{{CONTENT}}', '{{CSS}}', '{{JS}}', '{{MESSAGE}}'), 
			Array(App::$title, App::$meta, $content, App::$tpl -> loadCSS(), App::$tpl -> loadScripts(), $message), 
			$html
			);

		echo $html;
	}
	
	public static function getModuls() {
		return explode(',', Easy::MODULS);
	}

	public static function getLanguages() {
		$lngs = explode('|', Easy::languages);
		$result = array();
		foreach ($lngs as $key => $data) {
			$data = explode(':', $data);
			$shortcut = explode('_', $data[0]);
			$shortcut = $shortcut[0];
			$result[$data[0]] = array('key' => $shortcut, 'name' => $data[1]);
		}
		return $result;
	}

	public static function getLanguageCode($shortcut = 'hu') {
		$l = self::getLanguages();
		foreach ($l as $code => $data) {
			if ($data['key'] == $shortcut) {
				return $code;
			}
		}
		return false;
	}
	
	public static function getLanguageName($code = 'hu_HU') {
		$l = self::getLanguages();
		return $l[$code]['name'];
	}

	public static function getLanguageUrl($code = 'hu_HU') {
		$l = self::getLanguages();
		return $l[$code]['key'];
	}

	/**
	 * Get An Instance of current user object
	 * @return object User
	 */
	public static function getUser() {
		return User::getInstance();
	}

	/**
	 * Show 404 error page and sets 404 header
	 *
	 * @param string $message specific ErrorMessage to display
	 */
	public static function show404ErrorPage($message = '') {
		
		header("HTTP/1.0 404 Not Found");

		if (!defined('_ADMIN_')) {
			$tplDir = TEMPLATES . DS . Easy::template;
		} else {
			$tplDir = TEMPLATES . DS . Easy::template_admin;
		}
		
		App::$tpl = App::getTpl();
		if (is_file($tplDir . DS . 'tpl.settings.php')) {
			require $tplDir . DS . 'tpl.settings.php';
		}
		App::$tpl->addCSS('404.css', Easy::template, 'default');

		ob_Start();
			require dirname(dirname(__FILE__)).DS.'helpers'.DS.'404error.php';
			$content = ob_get_contents();
		ob_get_clean()>

		$TPL = $tplDir . DS . (!defined('_ADMIN_')?Easy::template:Easy::template_admin) . '.tpl';
		ob_start();
			include ($TPL);
			$html = ob_get_contents();
		ob_get_clean();

		$message = Message::getMessage();
		$html = str_replace(
			Array('{{TITLE}}', '{{META}}', '{{CONTENT}}', '{{CSS}}', '{{JS}}', '{{MESSAGE}}'), 
			Array(Format::html2txt($message), '', $content, App::$tpl -> loadCSS(), App::$tpl -> loadScripts(), $message), 
			$html
			);

		echo $html;
		exit();
	}
	
	/**
	 * Set the application meta data
	 *
	 * @param string $kw meta keywords
	 * @param string $desc meta description
	 */
	public static function setMeta($data = array()) {

		$html = '';

		$nl = "\r\n";
		$html .= isset($data['author']) ? ($nl. '<meta name="author" content="' . $data['author'] . '" />') : '';
		$html .= isset($data['keywords']) ? ($nl. '<meta name="keywords" content="' . $data['keywords'] . '" />') : '';
		$html .= isset($data['description']) ? ($nl. '<meta name="description" content="' . $data['description'] . '" />') : '';
		$html .= isset($data['url']) ? ($nl. '<meta name="og:url" content="' . $data['url'] . '" />') : '';
		$html .= isset($data['title']) ? ($nl. '<meta name="og:title" content="' . $data['title'] . '" />') : '';
		$html .= isset($data['description']) ? ($nl. '<meta name="og:description" content="' . $data['description'] . '" />') : '';
		$html .= isset($data['image']) ? ($nl. '<meta name="og:image" content="' . $data['image'] . '" />') : '';
		
		self::$meta = $html;
	}

	/**
	 * Get de default settings of the page
	 * for ex. desc length, title length, portal title etc.
	 */
	public static function _settings($id = 1) {
		if (!$id > 0)
			$id = 1;
		$_Q = 'SELECT * FROM portal WHERE id =' . (int)$id;
		$db = DB::getInstance();
		$db -> setQuery($_Q);
		
		$obj = $db->loadObject();
		$options = json_decode($obj->options);
		$options -> title = $obj->title;
		$options -> meta = $obj->meta;
		$options -> domain = $obj->main_domain;
		$options -> analytics = $obj->analytics;
		
		return $options;
	}
}
