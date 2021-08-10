<?php defined('__GX__') or die('Access denied!');

class Service {

	public static function invoke($service, $action, $data) {

		$app = new App;

		if (!defined('_ADMIN_')) {
			$file = PATH_CONTROLLERS . DS . $service . '.php';
		} else {
			$file = PATH_CONTROLLERS . DS . 'admin' . DS . $service . '.php';
		}

		if (is_file($file) && in_array($service, $app->getModuls())) {
			require $file;
			$className = ucfirst($service).'Controller';
			if (class_exists($className)) {
				$ServiceObject = new $className;
				$result = new ServiceResult();
				$ServiceObject -> data = $data;
				if (!method_exists($ServiceObject, 'Ajax_'.$action)) {
					$result -> setError(ServiceResult::ACTION_IS_NOT_VALID, 'ACTION_IS_NOT_VALID');
					return $result;
				}
				$result -> data = $ServiceObject->{'Ajax_'.$action}();
				return $result;
			}
			return;
		} else {
			$result = new ServiceResult();
			$result -> setError(ServiceResult::SERVICE_IS_NOT_VALID, 'SERVICE_IS_NOT_VALID');
			return $result;
		}
	}

	public static function my_exec($cmd, $input = '') {
		$proc = proc_open ( $cmd, array (0 => array ('pipe', 'r' ), 1 => array ('pipe', 'w' ), 2 => array ('pipe', 'w' ) ), $pipes );
		fwrite ( $pipes [0], $input );
		fclose ( $pipes [0] );
		$stdout = stream_get_contents ( $pipes [1] );
		fclose ( $pipes [1] );
		$stderr = stream_get_contents ( $pipes [2] );
		fclose ( $pipes [2] );
		$rtn = proc_close ( $proc );
		return array ('input'=>$cmd. ' '.$input , 'stdout' => $stdout, 'stderr' => $stderr, 'return' => $rtn );
	}

}

class ServiceResult {

	public $data;
	public $errorCode = 0;
	public $errorMsg = '';
	public $messages = array();
	public $warnings = array();
	public $onlyData = false;
	// ha ez true, akkor csak a data lesz a valaszba rakva.

	const ACTION_IS_NOT_VALID = 1;
	const SERVICE_IS_NOT_VALID = 2;
	const MODULE_ERROR = 3;

	/*
	 * ErrorCodes:
	 * 1 - ACTION_IS_NOT_VALID
	 * 2 - SERVICE_IS_NOT_VALID
	 * 3 - MODULE_ERROR
	 */

	public function setError($code, $msg = '') {
		$this -> errorCode = $code;
		$this -> errorMsg = $msg;
	}

	public function addInfo($msg, $customText = '') {
		$this -> messages[] = $msg . ' ' . $customText;
	}

	public function addWarning($msg, $customText = '') {
		$this -> warnings[] = $msg . ' ' . $customText;
	}

}
