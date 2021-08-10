<?php defined('__GX__') or die('Access denied!');

class Message extends EObject {
	
	CONST VERSION = '3.0.0';

	static function setMessage($msg = '', $msgTemplate = '') {
		$templatePath = (!defined('_ADMIN_')) ? 
			TEMPLATES . DS . Easy::template . DS . 'messages' . DS . $msgTemplate. '.tpl' :
			TEMPLATES . DS . Easy::template_admin . DS . 'messages' . DS . $msgTemplate. '.tpl';

		if (is_file($templatePath))
			$msg = str_replace( '{{MESSAGE}}', $msg, file_get_contents($templatePath));
		else {
			$msg = '<div id="system-message" class="message alert alert-info">'.$msg.'</div>';
		}

		Cookie::set('message', base64_encode($msg), ONE_HOUR);
	}

	static function getMessage() {
		$_message = base64_decode(Cookie::getVar('message'));
		Cookie::del('message');
		
		return $_message;
	}

}