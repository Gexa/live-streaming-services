<?php

defined('__GX__') or die('Access denied!');

abstract class ControllerBase {

	CONST VERSION = '3.0.0';
	
	protected $_rules = null;
	protected $_redirectTo = null;
	protected $_redirectMsg = null;
	
	protected $displayedView = null;
	protected $caching = false;

	function __construct() {
		$access = new Access();
		$this->access = $access->getRoles(); 
		$this -> _rules = '*';
		$this -> caching = Easy::caching && $this->caching;
		$this -> user = User::getInstance();
		$this -> _preInit();

		Import::_class('social');
		new Social();
	}

	protected function _preInit() {
	}

	public function caching() {
		return $this->caching && Easy::caching;
	}

	function display($tpl = null) {

		$this -> setRules($this -> _rules);
		if ($tpl == null) {
			$tpl = App::getAction();
		}

		$dir = App::getCtrl();

		if (!defined('_ADMIN_')) {
			$view = PATH_VIEWS . DS . $dir . DS . 'view.' . $tpl . '.php';
		} else {
			$view = PATH_VIEWS . DS . 'admin' . DS . $dir . DS . 'view.' . $tpl . '.php';
		}

		$tplView = '';
		if (!defined('_ADMIN_')) {
			$tplView = TEMPLATES . DS . Easy::template . DS . 'html' . DS . $dir . DS . 'view.' . $tpl . '.php';
		} else {
			$tplView = TEMPLATES . DS . Easy::template_admin . DS . 'html' . DS . $dir . DS . 'view.' . $tpl . '.php';
		}

		if (is_file($tplView)) {
			$view = $tplView;
		}

		if (!$this -> _rules()) {
			$tpl = 'login';
			if (!defined('_ADMIN_')) {
				if (is_string($this->_redirectTo) && strlen($this->_redirectTo)>0) {
					Router::redirect($this -> _redirectTo, ($this -> _redirectMsg != null) ? $this -> _redirectMsg : Text::_('global.no_access_role'));
				} else {
					$view = TEMPLATES . DS . Easy::template . DS . 'user' . DS . $tpl . '.tpl';
				}
			} elseif (defined('_ADMIN_')) {
				if (App::getCtrl() != 'default') {
					Router::redirect($this -> _redirectTo, ($this -> _redirectMsg != null) ? $this -> _redirectMsg : Text::_('global.no_access_role'));
					exit ;
				} else {
					$view = TEMPLATES . DS . Easy::template_admin . DS . 'user' . DS . $tpl . '.tpl';
				}
			}
		}

		if (!is_file($view)) {
			throw new Exception('View file doesn\'t exist: ' . $view);
		}
		
		ob_start();
			include ($view);
		$html = ob_get_contents();
		ob_get_clean();

		if (!defined('_AJAX_')) {
			print $html;
		} else {
			return self::ConvertToJSON($html);
		}
	}
	
	function ConvertToJSON($html = null) {
		return json_encode(array('html' => $html));
	}

	protected function _rules() {
		$enabled = false;
		$_user = App::getUser();
		$user = $_user -> getData();
		
		if (!count($this -> _rules) > 0 or $this -> _rules == '*') {
			$enabled = true;
		}
		
		$accessObj = new Access();
		$access = is_object($accessObj->getRoles()) ? $accessObj->getRoles()->access : null;
		
		if (!$user && $_user->loggedIn()) {
			App::getUser()->logoutUser();
		}

		if (is_array($this -> _rules) && $user != false && !is_array($access) && in_array((int)$user -> role, $this -> _rules)) {
			$enabled = true;
		} elseif (is_array($this -> _rules) && $user != false && is_array($access)) {
			$enabled = false;
			foreach($this->_rules as $rule) {
				if (in_array($rule, $access)) {
					$enabled = true;
				}
			}
		}

		return $enabled;
	}
	
	protected function setRules($rules) {

		if ((is_array($rules) && count($rules) > 0) or $rules == '*') {
			$this -> _rules = $rules;
		}
	}

	function getModel($modelName = '', $force_frontend = false) {
		if ($modelName == '' && !defined('_ADMIN_'))
			$file = PATH_MODELS . DS . App::getCtrl() . '.php';
		elseif (($modelName != '' && !defined('_ADMIN_')) || ($modelName != '' && $force_frontend == true))
			$file = PATH_MODELS . DS . $modelName . '.php';
		elseif ($modelName == '' && defined('_ADMIN_'))
			$file = PATH_MODELS . DS . 'admin' . DS . App::getCtrl() . '.php';
		elseif ($modelName != '' && defined('_ADMIN_'))
			$file = PATH_MODELS . DS . 'admin' . DS . $modelName . '.php';

		if (!file_exists($file)) {
			if (!defined('_AJAX_')) {
				die('Model does not exist: '.$file);
			} else {
				$result = new ServiceResult();
				$result -> error = 'Model file does not exist: '.$file;
				$result -> setError(ServiceResult::MODULE_ERROR, $result->error);
				die( json_encode($result) );
			}
		}
		
		require $file;

		$modelName = ($modelName != '' ? ucfirst($modelName) : ucfirst(App::getCtrl())) . 'Model';
		
		return new $modelName();
	}
	
}