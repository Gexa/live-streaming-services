<?php defined('__GX__') or die('Access denied!');

class User extends EObject {
	
	CONST VERSION = '3.0.0';
	const SALT = 'Ea$yManagE2014_';
	protected $data = null;
	
	public function __construct() {

		$accessMD5 = Session::getVar('_emSeU');
		if (strlen($accessMD5) == 32) {
			$this->login($accessMD5);
		} else {
			$this->login();
		}
	}
	
	public static function loggedIn() {
		$u = Session::getVar('_emSeU');
		$db = DB::getInstance();

		$login_hash = Session::getVar('loginHash', '');
		$_Q = 'SELECT 1 FROM users WHERE active >= 1 AND CASE WHEN MD5_ID IS NULL THEN (MD5(CONCAT("'.self::SALT.'", users.id))='.$db->quote($u).') ELSE MD5_ID='.$db->quote($u).' END AND login_hash LIKE '.$db->quote($login_hash);

		$db->setQuery($_Q);

		if ((int)$db->loadResult() == 1) {
			return true;
		} elseif (strlen($u) >= 32) {
			Session::del('_emSeU');
			return false;
		}
		return false;
	}


	
	/**
	* User Login method, with redirect
	* 
	* @param mixed $hash
	* @param mixed $redirectTo - redirect To URL
	* @param mixed $redirectMessage - redirect Message on success
	*/
	public function login($hash = '', $redirectTo = '', $redirectMessage = '') {
		
		$needActivation = (Easy::UserActivationRequired == 1) ? true : false;
		
		$db = DB::getInstance();
		$request_login = false;
		
		if (isset($hash) && $hash != '') {
			$login_hash = Session::getVar('loginHash', '');
			$_Q = 'SELECT * FROM users WHERE active = 1 AND CASE WHEN MD5_ID IS NULL THEN (MD5(CONCAT("'.self::SALT.'", users.id))='.$db->quote($hash).') ELSE MD5_ID='.$db->quote($hash).' END AND login_hash LIKE '.$db->quote($login_hash);
		} elseif ( 
			Request::isPost() && 
			(Request::getVar('login_email', null)!=null or Request::getVar('login_username', null)!=null) && 
			Request::getVar('login_pass', null)!=null ) {
			
			$credential = (Request::getVar('login_email', null)!=null) ? 
								Request::getVar('login_email') : 
								Request::getVar('login_username');
			
			$pass = md5(Request::getVar('login_pass'));
			
			$SQL_credential = ' email';
			/*if (strpos( $credential, '@')>0) {
				$SQL_credential = ' email';
			} else $SQL_credential = ' username';*/
			
			$_Q = 'SELECT * FROM users WHERE '.$SQL_credential.'='.$db->quote($credential).' AND password='.$db->quote($pass). (($needActivation)?' AND active=1 ' : '');
			
			$request_login = true;
			
		} elseif (Request::isPost() && isset($_REQUEST['login_pass']) )  {
			Message::setMessage(Text::_('global.login_failed'), 'error');
			return false;
		} else {
			return false;
		}
		
		$db->setQuery($_Q);
		$user = $db->loadObjectList();
		
		if (!isset($user[0])) {
			Message::setMessage(Text::_('global.login_failed'), 'error');
			return false;
		}
		$this->data = $user[0];

		if ($this->getData() != false && $hash == '') {
			Session::set('_emSeU', md5(self::SALT . $this->data->id));
		}
		
		if (defined('_ADMIN_') && $request_login) {
			$_REQUEST['login_pass'] = null;
		}
		
		if (strlen($redirectTo) > 0 && $this->getData() != false) {
		    Router::redirect($redirectTo, $redirectMessage);
		} elseif ($hash == '' && $this->getData() != false) {
			$login_hash = sha1(time().Request::realIP());
			Session::set('loginHash', $login_hash);
			self::_log('User ('.$this->data->email.') logged in at '.strftime('%Y. %B %d %H:%M', time()));
			$db->setQuery('UPDATE users SET last_login = now(), login_hash='.$db->quote($login_hash).' WHERE id = '.(int)$this->data->id);
			$db->query();
			//Message::setMessage(Text::_('global.login_success'));
		}
	}
	
	/**
	* User logout method with redirect (like login)
	* 
	* @param mixed $redirectTo
	* @param mixed $redirectMessage
	*/
	public function logoutUser($redirectTo = '', $redirectMessage = '') {
		
		self::_log('User ('.$this->data->email.') logged out at '.strftime('%Y. %B %d %H:%M', time()));
			
		if (isset($_COOKIE['chat_opened'])) {
			setcookie('chat_opened', '', -1, URL_BASE);
			setcookie('chat_status', '', -1, URL_BASE);
		}

		$db = DB::getInstance();
		$db->setQuery('UPDATE users SET status="offline" WHERE id='.$this->data->id);
		$db->query();

		$this->data = null;
		Session::del('_emSeU');

		$dir = EXTENSION . DS . 'OauthLogin' . DS;
		require $dir . 'header.php';
		require $dir . 'facebook_lib/facebook.php';
		require $dir . 'facebook_lib/config.php';
		
		$_SESSION['google_token'] = null;
		unset($_SESSION['google_token']);
		unset($_SESSION['google_data']);
		unset($_SESSION['OAUTH_STATE']);
		unset($_SESSION['OAUTH_ACCESS_TOKEN']);

		$user = $facebook->getUser();
		if ($facebook->getAccessToken() && $user) {
			$redirectTo = $facebook->getLogoutUrl(Array(
				'next' => (Format::getProtocol().$_SERVER['HTTP_HOST'].URL_BASE)
				));
			$facebook->destroySession();
		}

		if (strlen($redirectTo) > 0) {
			$redirectMessage = '';
			Router::redirect($redirectTo, $redirectMessage);
		} elseif (!strlen($redirectTo)>0 && strlen($redirectMessage)>0) {
			//Message::setMessage($redirectMessage);
		}
		
	}
	
	/**
	* Get all basic data of user
	*/
	public function getData() {
		
		if (!$this->data)
			return false;
		
		return $this -> data;
	}

	
	/**
	* Get User Access role (1: Registered user, 10: Admin, 100: SuperAdmin) 
	*/
	public function getRole() {
		return $this->data->role;
	}
	
	
	/**
	* Log user events
	*/
	public function _log($event) {
		
		if (!$this->data) {
			return false;
		}
		
		$log = (array)json_decode($this->data->log);
		$log_archive = false;
		if (!$log) {
			$log = array();
		} elseif (count($log) >= 100) {
			$log_archive = (array)$log;
			$log = array();
		}

		$d = date('Y-m-d H:i:s');
		$log[$d] = array( 
				'event' => $event,
				'ip' => Request::realIP()
				);
		$db = DB::getInstance();

		if ($log_archive != false) {
			$db->setQuery('SELECT log FROM users_log_archive WHERE userid = '.(int)$this->data->id);
			$old_log = $db->loadResult();
			if (!$old_log) {
				$db->setQuery('INSERT INTO users_log_archive (userid, log) VALUES ('.(int)$this->data->id.', '.$db->quote(json_encode($log_archive)).');');
				$db->query();
			} else {
				$db->setQuery('UPDATE users_log_archive SET log = '.$db->quote(json_encode($log_archive)).' WHERE userid = '.(int)$this->data->id);
				$db->query();
			}
		}

		$db->setQuery('UPDATE users SET log = '.$db->quote(json_encode($log)).' WHERE id = '.(int)$this->data->id);
		if (!$db->query()) {
			return false;
		}

		return true;

	}

	static function getUserLog( $id = null, $offset = false, $limit = false ) {
		if (!(int)$id)
			return false;
		
		$db = DB::getInstance();

		$db->setQuery('SELECT log FROM users WHERE id = '.$id);
		$log = array_reverse((array)json_decode($db->loadResult()));
		
		$db->setQuery('SELECT log FROM users_log_archive WHERE userid = '.(int)$id);
		$log_archive = array_reverse((array)json_decode($db->loadResult()));
		if (empty($log_archive))
			return !$offset && !$limit ? count($log) : 
					array_slice($log, $offset, $limit);

		$allLog = array_merge($log, $log_archive);

		return !$offset && !$limit ? 
			count(array_merge($log, $log_archive)) : 
			array_slice($allLog, (int)$offset, (int)$limit);
	}
		
}