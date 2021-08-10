<?php defined('__GX__') or die('Access denied!');

class Newsletter extends EObject {

	private static $config = Array('from_name' => null, 'from_email' => null);

	public static function setData() {

		$db = DB::getInstance();
		
		$db->setQuery('SET NAMES utf8 COLLATE utf8_general_ci');
		$db->query();
		
		$db -> setQuery('SELECT * FROM newsletter_settings WHERE 1 LIMIT 1');

		$obj = $db -> loadObject();

		self::$config['from_email'] = $obj -> from_email;
		self::$config['from_name'] = $obj -> from_name;
		

	}

	public static function sendDelayedNewsletter() {

		$db = DB::getInstance();
		$db -> setQuery('SELECT * FROM newsletter_data WHERE delayed_send!="0000-00-00 00:00:00" AND (sent IS NULL OR sent = 0) AND delayed_send < now() ');
		$list = $db -> loadObjectList();
		
		if (count($list) > 0) {

			$sent = 0;
			foreach ($list AS $letter) {

				$data = Array(
					'name' => $letter -> subject, 
					'content' => $letter -> content, 
					'group' => json_decode($letter -> groups), 
					'language' => $letter -> language, 
					'delayed_send' => '0000-00-00 00:00:00', 
					'id' => $letter -> id
					);
				
				self::sendNewsletter($data, false);
				$sent++;
			}

			if ($sent>0) {
				return true;
			}

		}

		return false;
		
	}

	public static function sendNewsletter($data) {
		
		date_default_timezone_set('Europe/Budapest');
		$db = DB::getInstance();
		
		if (!$data)
			return false;
		
		$id = (int)$data['id'];
		
		// Késleltetett küldés. Csak akkor küldi ki azonnal ha nincs megadva delayed_send
		if (trim($data['delayed_send']) != '' && $data['delayed_send'] != '0000-00-00 00:00:00') {
			return true;
		}

		$subject = isset($data['name']) && strlen($data['name']) > 1 ? Format::makeSafe($data['name']) : Text::_('global.no_subject');
		$groups = $data['group'];
		$settings = self::setData();
		$sent_count = 0;
		$failed = 0;
		
		$db->setQuery('SELECT main_domain FROM portal WHERE id=1');
		$d1 = $db->loadResult();
		
		$domain = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST']!='' && !is_null($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $d1;
		
		$params = Array(
			'sender_email' => self::$config['from_email'], 
			'sender_name' => self::$config['from_name'], 
			'BCC' => null);

		$crediental = Array(
			'smtp_port' => Easy::smtp_port, 
			'smtp_host' => Easy::smtp_host, 
			'smtp_secure' => Easy::smtp_secure, 
			'smtp_user' => Easy::smtp_user, 
			'smtp_pass' => Easy::smtp_pass
		);
		
		$mailer = Mailer::create($params, $crediental);
		$mailer -> addCustomHeader('Precedence: bulk');
		$mailer -> CharSet = Easy::charset;
		$mailer -> SMTPDebug = Easy::debug != 0 ? 2 : false;
		$mailer -> Subject = Format::makeSafe($subject);
		$mailer -> isHTML();
		
		
		// STORE users to newsletter
		$db->setQuery('SELECT users_sent FROM newsletter_sends WHERE nwl_id='.(int)$id);
		$users_sends = $db->loadResult();
		
		if (!$users_sends) {
			$db->setQuery('INSERT INTO newsletter_sends (nwl_id, users_sent) VALUES ('.(int)$id.', "")');
			$db->query();
			$users_sends = Array();
		} elseif (!is_null($users_sends) && strlen($users_sends)>1) {
			$users_sends = json_decode($users_sends);
		} else {
			$users_sends = Array();
		}
		
		
		$db -> setQuery('UPDATE newsletter_data SET time_send=now(), sent=1, failed=0, sent_count=0, sents=(sents+1) WHERE id=' . (int)$id);
		$db -> query();
		
		foreach ($groups AS $gID) {
			
			$db->setQuery('SELECT * FROM newsletter_subscribers WHERE warn_level < 10 AND active=1 AND group_id='.(int)$gID . (count($users_sends) > 0 ? (' AND subscriber_id NOT IN ('.implode(',', $users_sends).') ') : '' ) );
			$userList = $db->loadObjectList();
			
			// 365
			$repeat = ceil((count($userList) / 100)); // 4
			$x = 0;
			$send_counter = count($userList);
			foreach($userList AS $user) {
				
				$mailer -> ClearAllRecipients();
				$mailer -> ClearAddresses();
				$mailer -> ClearBCCs();
			
				if (!Format::validateEmail($user->subscriber_email)) { 
					$db->setQuery('UPDATE newsletter_subscribers SET warn_level=10 WHERE subscriber_id='.(int)$user->subscriber_id);
					$db->query();
					$failed++;
				} else {
					$unsubscribe_link = '
							<a href="http://'.$domain.'/unsubscribe/'.md5( 'EasyManage2.0' . $user->subscriber_id ).'/">'.
								Text::_('newsletter.unsubscribe').
							'</a>';
						
				
					$body = str_replace(
								Array(
									'{tag:name}',
									'{tag:tagname}',
									'{tag:date}', 
									'{tag:email}', 
									'{tag:unsubscribe}', 
									'"/storage/', 
									"'/storage/"), 
								Array(
									$user->subscriber_name, 
									$user->subscriber_name, 
									strftime('%Y. %B %d. %R', time()), 
									$user->subscriber_email, 
									$unsubscribe_link,
									'"http://'.$domain.'/storage/',
									"'http://".$domain."/storage/"
									),
								stripslashes($data['content']));
					
					$body .= '<img src="http://'.$domain.'/tracking?id='.$id.'&userid='.(int)$user->subscriber_id.'" style="border: 0; width: 0; height: 0;" />';
					
					$mailer -> Body = '
						<!DOCTYPE html>
						<html>
							<head>
								<meta charset="UTF-8" />
								<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
							</head>
							<body>
								'.$body.'
							</body>
						</html>
					';
					
					$mailer -> AddAddress($user -> subscriber_email);
					try {
						if (!$mailer -> Send()) {
							$mailer->smtpReset();
							$failed++;
						}
						$users_sends[] = (int)$user->subscriber_id;
						$sent_count++;
					} catch (phpmailerException $e) {
						echo $e->errorMessage();
						$mailer->smtpReset();
						$failed++;
					} catch (Exception $e) {
						echo $e->getMessage();
						$mailer->smtpReset();
						$failed++;
					}
				}
			}
			
			$db->setQuery('UPDATE newsletter_sends SET users_sent='.$db->quote(json_encode($users_sends)).' WHERE nwl_id='.(int)$id);
			$db->query();
			
			$x++;
		}
		
		$db -> setQuery('UPDATE newsletter_data SET time_send=now(), sent=1, failed=(failed+'.(int)$failed.'), sent_count=(sent_count+'.(int)$sent_count.'), sents=(sents+1) WHERE id=' . (int)$id);
		$db -> query();
		
		return true;
		
	}

	/* Validate an email address using SMTP. */
	function ValidateEmailUsingSMTP($sToEmail, $sFromDomain = null, $sFromEmail = null, $bIsDebug = false) {
		
		if (is_null($sFromEmail))
			$sFromEmail = Easy::smtp_user;
		
		$dArr = explode('@', Easy::smtp_user);
		if (is_null($sFromDomain))
			$sFromDomain = $dArr[1];
		
		$bIsValid = true; // assume the address is valid by default..
		$aEmailParts = explode("@", $sToEmail); // extract the user/domain..
		getmxrr($aEmailParts[1], $aMatches); // get the mx records..
	
		if (sizeof($aMatches) == 0) {
			return false; // no mx records..
		}
	
		foreach ($aMatches as $oValue) {
			if ($bIsValid && !isset($sResponseCode)) {
				// open the connection..
				$oConnection = @fsockopen($oValue, 25, $errno, $errstr, 30);
				$oResponse = @fgets($oConnection);
				if (!$oConnection) {
					$aConnectionLog['Connection'] = "ERROR";
					$aConnectionLog['ConnectionResponse'] = $errstr;
					$bIsValid = false; // unable to connect..
				} else {
					$aConnectionLog['Connection'] = "SUCCESS";
					$aConnectionLog['ConnectionResponse'] = $errstr;
					$bIsValid = true; // so far so good..
				}
				
				if (!$bIsValid) {
					if ($bIsDebug) print_r($aConnectionLog);
					return false;
				}
	
				// say hello to the server..
				fputs($oConnection, "HELO $sFromDomain\r\n");
				$oResponse = fgets($oConnection);
				$aConnectionLog['HELO'] = $oResponse;
	
				// send the email from..
				fputs($oConnection, "MAIL FROM: <$sFromEmail>\r\n");
				$oResponse = fgets($oConnection);
				$aConnectionLog['MailFromResponse'] = $oResponse;
	
				// send the email to..
				fputs($oConnection, "RCPT TO: <$sToEmail>\r\n");
				$oResponse = fgets($oConnection);
				$aConnectionLog['MailToResponse'] = $oResponse;
	
				// get the response code..
				$sResponseCode = substr($aConnectionLog['MailToResponse'], 0, 3);
				$sBaseResponseCode = substr($sResponseCode, 0, 1);
	
				// say goodbye..
				fputs($oConnection,"QUIT\r\n");
				$oResponse = fgets($oConnection);
	
				// get the quit code and response..
				$aConnectionLog['QuitResponse'] = $oResponse;
				$aConnectionLog['QuitCode'] = substr($oResponse, 0, 3);
				if ($sBaseResponseCode == "5") {
					$bIsValid = false; // the address is not valid..
				}
				// close the connection..
				@fclose($oConnection);
			}
		}
	
		if ($bIsDebug) {
			print_r($aConnectionLog); // output debug info..
		}
	
		return $bIsValid;
	
	}

}
