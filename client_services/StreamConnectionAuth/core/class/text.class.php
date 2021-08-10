<?php defined ( '__GX__' ) or die ( 'ACCESS DENIED!' );

class Text {
	
	CONST VERSION = '3.0.0';
	
	/**
	* function to get language texts
	* 
	* @param mixed $var
	*/
	public static function _($var) {
		
		$lng = Easy::$Language;
		
		$userLang = Cookie::getVar('_lng');
		if (isset($userLang) && strlen($userLang)>0)
			$lng = Cookie::getVar('_lng');
		
		$ctrl = Request::getVar('controller', null, 'STRING');
		
		$Lang = TEMPLATES . DS . (!defined('_ADMIN_') ? Easy::template : Easy::template_admin) . DS . 'language' . DS . Easy::$Language . '.php';
		if (is_file($Lang)) {
			include $Lang;
		}
		
		if (isset($EasyLang) && is_array($EasyLang) && isset($EasyLang[$var])) {
			
			return ($EasyLang[$var]);
			
		} else return trim('{'.$var.'}');
		
	}
	
	/**
	* sprintf in language string
	* 
	* @param mixed $var, Language Text key
	* @param mixed $arg1, $arg2 etc. Replacements
	*/
	public static function _sprintf($var) {
		$args = func_get_args();
		unset($args[0]);
		return vsprintf(self::_($var), $args);
	}
	
}
  
?>
