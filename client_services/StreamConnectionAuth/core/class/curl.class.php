<?php defined('__GX__') or die('Access denied!');

/** 
* @package cURL
* @author Gexa
**/

interface URLInterface {
	// init cURL with given params
	public static function init($url = '', $params = array());
	// get result
	public static function get();
	// destroy cURL instance
	public static function destroy();
}

class cURL implements URLInterface {

	private static $instance = null;

	public static function init($url = '', $params = array()) {
		self::$instance = curl_init();
		curl_setopt_array(self::$instance, array(
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_URL => $url,
			CURLOPT_POST => !isset($params['method']) || strtolower($params['method']) != 'post' ? 0 : 1,
			CURLOPT_POSTFIELDS => isset($params['fields']) ? $params['fields'] : array()
		));
	}

	public static function get() {
		if (self::$instance) {
			try {
				$result = curl_exec(self::$instance);
				if (!$result) {
					return false;
				}
				self::destroy();
				return $result;
			} catch (Exception $e) {
				throw new Exception('Error: "' . curl_error(self::$instance) . '" - Code: ' . curl_errno(self::$instance));
			}
		} else return false;
	}

	public static function destroy() {
		if (self::$instance) {
			curl_close(self::$instance);
			self::$instance = null;
		}
	}
}

/*********************************** USAGE *******************************
$h = cURL::init('http://localhost/test.html', array('method' => 'GET'));
$result = cURL::get();
echo $result;
**************************************************************************/