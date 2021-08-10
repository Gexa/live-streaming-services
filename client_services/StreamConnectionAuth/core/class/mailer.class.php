<?php

defined('__GX__') or die('Access denied!');

class Mailer extends EObject {

	/**
	 * Setting up mailer object
	 */
	public static function create($params = Array(), $crediental = null) {
		
		Extension::load('phpmailer');
		$mailer = new PHPMailer(true);
		
		$mailer -> SMTPOptions = array(
			'ssl'=> array(
				'verify_peer' => false,
				'verify_peer_name' => false,
				'allow_self_signed' => true
				)
			);
		
		$mailer -> CharSet = Easy::charset;
		
		$mailer -> From = (isset($params['sender_email'])) ? $params['sender_email'] : Easy::owner_email;
		$mailer -> FromName = (isset($params['sender_name'])) ? 
									$params['sender_name'] : 
									Easy::owner_name;
	
		if (!isset($params['BCC']))
			$mailer -> AddBCC(Easy::owner_email, Easy::owner_name);

		if (Easy::smtp_enabled && $crediental == null) {
			$mailer -> IsSMTP();
			$mailer -> SMTPSecure = Easy::smtp_secure;
			$mailer -> SMTPAuth = true;
			$mailer -> Host = Easy::smtp_host;
			$mailer -> Port = Easy::smtp_port;
			$mailer -> Username = Easy::smtp_user;
			$mailer -> Password = Easy::smtp_pass;
		} elseif ($crediental != null && is_array($crediental)) {
			$mailer -> IsSMTP();
			$mailer -> SMTPSecure = $crediental['smtp_secure'];
			$mailer -> SMTPAuth = true;
			$mailer -> Host = $crediental['smtp_host'];
			$mailer -> Port = $crediental['smtp_port'];
			$mailer -> Username = $crediental['smtp_user'];
			$mailer -> Password = $crediental['smtp_pass'];
		} else {
			$mailer -> Sendmail = @ini_get('sendmail_path');
			$mailer -> IsSendmail();
		}

		$mailer->AddReplyTo(Easy::owner_email);
		
		
		return $mailer;
	}

}
