<?php defined ( '__GX__' ) or die ( 'ACCESS DENIED!' );

class CRON {

	CONST VERSION = '3.0.0';
	private static $db = null;
	private static $domain = 'spirittars.hu';
	private static $template = '{{BODY}}';
	
	public static function run($action = null, $params = array()) {

		setlocale(LC_ALL, Easy::$Language.'.'.Easy::charset);
		
		Format::_('CRON daemon started at <b>'.strftime('%Y. %B %d. %H:%m', time()).'</b>');
		$action = 'action_'.Format::makeSafe($action);
		self::$domain = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] != '' ? $_SERVER['HTTP_HOST'] : self::$domain;

		// LAST RUNNING
		$CRON = BASE_PATH . DS . '_last_cron_run_';
		file_put_contents($CRON, time());
		
		ini_set('max_execution_time', 3600);
		ini_set('memory_limit', '2G');

		self::$action($params);
	}
	
	private static function action_refresh($params = array()) {
		var_dump('refreshing site');
	}

	private static function action_cleanup($params = array()) {
		var_dump('cleaning up site');
		self::action_refresh();
	}

	private static function action_sendgroupletter($params = array()) {
		syslog(1, 'CRONJOB started at '.date('Y-m-d H:i:s').': sendgroupletters');

		$template_path = TEMPLATES . DS . 'spirittars' . DS . 'emails' . DS . 'mail.base.tpl';
		if (file_exists($template_path)) {
			self::$template = '<link href="https://fonts.googleapis.com/css?family=Open+Sans:400,400i,700,700i&amp;subset=latin-ext" rel="stylesheet">'.str_replace(array('{{DOMAIN}}', '{{PROJECTNAME}}'), array('http://'.self::$domain, Easy::ProjectName), file_get_contents($template_path));
		}

		if (!self::$db)self::$db = DB::getInstance();

		self::$db->setQuery('SELECT * FROM user_group_email uge JOIN user_groups ug ON ug.ug_id=group_id WHERE sent IS NULL');
		$list = self::$db->loadObjectList();

		Import::_class('spirittars');

		
		error_reporting(E_ALL);
		ini_set('display_errors', 1);

		foreach ($list as $key => $to_send) {
			$query = '';
			parse_str($to_send->params, $query);
			if (!is_array($query))continue;

			$content = $to_send->content;
			$subject = $to_send->subject;

			$mailer = Mailer::create();
			$query['search']['only_nwl'] = false;
			$users = SpiritTars::getUserList($query['offset'], $query['limit'], $query['search'], false);
			
			$original_body = str_replace('{{BODY}}', $content, self::$template);
			$original_subject = $subject;
			foreach ($users as $key => $user) {
				$body = str_replace(array('{{tag:username}}', '{{tag:email}}', '{{tag:fullname}}'), array($user->username,$user->email,($user->lastname.' '.$user->firstname)), $original_body);
				$mailer->clearAllRecipients();
				$mailer->AddAddress($user->email);
				$mailer->Subject = str_replace(array('{{tag:username}}', '{{tag:email}}', '{{tag:fullname}}'), array($user->username,$user->email,($user->lastname.' '.$user->firstname)), $original_subject);
				$mailer->isHTML();
				$mailer->Body = $body;

				try {
					if (!$mailer->Send()) {
						continue;
					}
				} catch (Exception $e) {
					$mailer->smtpReset();
					continue;
				} catch (PHPMailerException $e) {
					$mailer->smtpReset();
					continue;
				}

			}

			self::$db->setQuery('UPDATE user_group_email SET sent=NOW() WHERE ugu_id='.$to_send->ugu_id);
			self::$db->query();

			sleep(2);
		}
	}

	private static function action_sendnewsletter($params = array()) {
		if (!Newsletter::sendDelayedNewsletter()) {
			print('<strong>No Newsletter to send</strong><br /><small>/Check newsletters if you think it\'s an error or contact '.Easy::owner_email.'/</small>');
		}
	}

	private static function action_resend_regmail($params = array()) {
		syslog(1, 'CRONJOB started at '.date('Y-m-d H:i:s').': resend_regmail');
		error_reporting(E_ALL);
		ini_Set('display_errors' ,1);
		/*$template_path = TEMPLATES . DS . 'spirittars' . DS . 'emails' . DS . 'mail.base.tpl';
		if (file_exists($template_path)) {
			self::$template = str_replace(array('{{DOMAIN}}', '{{PROJECTNAME}}'), array('http://'.self::$domain, Easy::ProjectName), file_get_contents($template_path));
		}*/
		$template = '<link href="https://fonts.googleapis.com/css?family=Open+Sans:400,400i,700,700i&amp;subset=latin-ext" rel="stylesheet">{{BODY}}';

		if (!self::$db)self::$db = DB::getInstance();

		// HA régebbi a reg. mint 3 nap
		$checkDate = date('Y-m-d H:i:s', strtotime('-3 days'));
		self::$db->setQuery('SELECT * FROM users_dating_reg WHERE notify=0 AND reg_date < '.self::$db->quote($checkDate));
		$list = self::$db->loadObjectList();

		if (count($list) == 0) return false;

		$mailer = Mailer::create();
		foreach ($list as $key => $user) {
			$token = md5(User::SALT.$user->email.$user->id);
			$activatelink = '<a href="http://'.self::$domain.Router::_('dating->register', 'token='.$token).'">'.Text::_('user.dating.register_text').'</a>';

			$mailDir = TEMPLATES . DS . Easy::template . DS . 'emails';
			$mailTemplate = $mailDir . DS . Easy::$Language . '.dating_reg_reminder.tpl';

			if (!is_file($mailTemplate)) {
				$mailTemplate = $mailDir . DS . 'default.dating_reg_reminder.tpl';
			}

			$body = file_get_contents($mailTemplate);
			$body = str_replace(
				Array('{{PROJECTNAME}}', '{{ACTIVATION}}'), 
				Array(Easy::ProjectName, $activatelink), 
				$body);

			$body = str_replace('{{BODY}}', $body, $template);
			$mailer -> Subject = Text::_('user.dating.register_reminder_subject');
			$name = Text::_('user.dating.register_name');
			$mailer -> clearAllRecipients();
			$mailer -> AddAddress($user->email, $name);
			$mailer -> MsgHTML($body);
			if (!$mailer -> Send()) {
				$duiq = 'DELETE FROM users_dating_reg WHERE id = '.$user->id;
				self::$db->setQuery($duiq);
				self::$db->query();
				$mailer->smtpReset();
			} else {
				self::$db->setQuery('UPDATE users_dating_reg SET notify = 1 WHERE id='.$user->id);
				self::$db->query();
			}
		}

		syslog(1, 'CRONJOB ended at '.date('Y-m-d H:i:s').': resend_regmail');
	}

	private static function action_payment_notification($params = array()) {
		syslog(1, 'CRONJOB started at '.date('Y-m-d H:i:s').': payment_notification');
		Import::_class('spirittars');

		$list = SpiritTars::getUserList(0, 5000, array('payment' => 4, 'soon_end' => 1, 'status' => 1));
		if (!count($list)) return false;

		$template_path = TEMPLATES . DS . 'spirittars' . DS . 'emails' . DS . 'mail.base.tpl';
		if (file_exists($template_path)) {
			self::$template = '<link href="https://fonts.googleapis.com/css?family=Open+Sans:400,400i,700,700i&amp;subset=latin-ext" rel="stylesheet">'.str_replace(array('{{DOMAIN}}', '{{PROJECTNAME}}'), array('http://'.self::$domain, Easy::ProjectName), file_get_contents($template_path));
		}

		$mailer = Mailer::create();
		foreach ($list as $key => $user) {
			$body = str_replace('{{BODY}}', Text::_sprintf('user.dating.payment_notification', $user->username, strftime('%Y. %B %d, %A', strtotime($user->activate_end))), self::$template);
			$mailer -> Subject = Text::_('user.dating.payment_notification_subject');
			$mailer -> clearAllRecipients();
			$mailer -> AddAddress($user->email, $user->username);
			$mailer -> MsgHTML($body);
			if ($mailer -> Send()) {
				echo 'Levél elküldve: '.$user->email.'<br>';
			}
		}
		syslog(1, 'CRONJOB ended at '.date('Y-m-d H:i:s').': payment_notification');
	}

	private static function action_senduserletters($params = array()) {

		syslog(1, 'CRONJOB started at '.date('Y-m-d H:i:s').': senduserletters');

		$template_path = TEMPLATES . DS . 'spirittars' . DS . 'emails' . DS . 'mail.base.tpl';
		if (file_exists($template_path)) {
			self::$template = '<link href="https://fonts.googleapis.com/css?family=Open+Sans:400,400i,700,700i&amp;subset=latin-ext" rel="stylesheet">'.str_replace(array('{{DOMAIN}}', '{{PROJECTNAME}}'), array('http://'.self::$domain, Easy::ProjectName), file_get_contents($template_path));
		}

		if (!self::$db)self::$db = DB::getInstance();
		self::$db->setQuery('SELECT * FROM users_nwl_settings JOIN users ON users.id=user_id WHERE users.active=1 ');
		$list = self::$db->loadObjectList();
		foreach ($list as $key => $user) {
			if ($user->frequency == 0)continue;
			$opts = json_decode($user->type);
			$sent = strtotime($user->last_sent);

			// Ha módosul egy esemény azonnal értesítő mindenkinek aki kért valamilyen formában értesítést...
			if ($user->frequency>0) {
				self::getEventsModified($user->user_id, $opts);
				self::getEventsDeleted($user->user_id, $opts);
			}

			//var_dump($opts);
			switch ($user->frequency) {
				default:
				case 1:
					// DONT CARE sent 
					self::generateMails($user->user_id, $opts);
					//var_dump('always');
					break;
				case 2:
					$lastday = time() - 3600 * 24;
					if ($sent < $lastday) {
						self::generateMails($user->user_id, $opts);
					}
					//var_dump('daily');
					break;
				case 3:
					$lastday = time() - 3600 * 24 * 7;
					if ($sent < $lastday) {
						self::generateMails($user->user_id, $opts);
					}
					//var_dump('weekly');
					break;
				case 4:
					$lastday = time() - 3600 * 24 * 30;
					if ($sent < $lastday) {
						self::generateMails($user->user_id, $opts);
					}
					//var_dump('monthly');
					break;
			}

		}

		syslog(1, 'CRONJOB ended at '.date('Y-m-d H:i:s').': senduserletters');
	}

	private static function generateMails($userId = null, $opts = array()) {
		if (!$userId || empty($opts))return false;
		self::getNewMessages($userId, $opts);
		self::getNewLikes($userId, $opts);
		self::getNewUsersBySearch($userId, $opts);
		self::getNewEventsNear($userId, $opts);
		self::getEventNewRegs($userId, $opts);
		self::getEventsNotify($user->user_id, $opts);
		
		if (!self::$db)self::$db = DB::getInstance();
		self::$db->setQuery('UPDATE users_nwl_settings SET last_sent = NOW() WHERE user_id='.$userId);
		self::$db->query();
	}

	private static function getNewMessages($userId = null, $opts = array()) {
		if (!$userId)return false;
		if (!in_array('message', $opts)) {
			return false;
		}
		if (!self::$db)self::$db = DB::getInstance();
		$user = self::getUser($userId);
		if (!$user) {
			return false;
		}

		self::$db->setQuery('SELECT DISTINCT udm.user_id, profile, username, udm.id AS msg_id 
								FROM users_dating_messages udm 
									JOIN users_dating_datas udd ON (udd.user_id=udm.user_id) 
									JOIN users u ON u.id=udm.user_id 
								WHERE udm.seen IS NULL AND udm.notify IS NULL AND udm.to_id='.$userId. ' AND u.active=1 GROUP BY user_id ');

		$messages = self::$db->loadObjectList();
		if (!count($messages)) {
			return false;
		}

		$template = self::$template;
		
		$body = '<h3>'. Text::_sprintf('user.notification.welcome', $user->username) .'</h3>';
		//$body .= '<p>'.Text::_sprintf('user.notification.messages', count($messages)).'</p>';
		$body .= '<p>'.Text::_('user.notification.messages_description').'</p>';
		$body .= '<p>';

		$msg_notify = array();
		foreach ($messages as $key => $msg) {
			$tnbl = Format::getThumbnail($msg->profile, '120x120');
			$body .= '<img src="'.(Format::getProtocol().self::$domain.$tnbl['url']).'" width="120" style="border-radius: 10px; margin: 5px;" title="'.stripslashes($msg->username).'" alt="'.($msg->username).'">&nbsp;&nbsp;';
			$msg_notify[] = $msg->msg_id;
		}
		$body .= '</p>';

		//self::$db->setQuery('UPDATE users_dating_messages SET notify=1 WHERE id IN ('.implode(',', $msg_notify).')');
		self::$db->setQuery('UPDATE users_dating_messages SET notify=1 WHERE seen IS NULL AND notify IS NULL AND to_id='.$userId);
		self::$db->query();


		$subject = Text::_sprintf('user.notification.messages', count($messages));
		$body = str_replace('{{BODY}}', $body, $template);

		return self::send($subject, $body, $user->email);

	}

	private static function getNewLikes($userId = null, $opts = array()) {
		if (!$userId)return false;
		if (!in_array('like', $opts)) {
			return false;
		}
		if (!self::$db)self::$db = DB::getInstance();
		$user = self::getUser($userId);
		if (!$user) {
			return false;
		}

		self::$db->setQuery('SELECT DISTINCT ul.user_id, profile, username, ul.id AS like_id 
								FROM user_likes ul 
									JOIN users_dating_datas udd ON (udd.user_id=ul.user_id) 
									JOIN users u ON u.id=ul.user_id 
								WHERE (ul.seen IS NULL OR ul.seen=0) AND ul.notify IS NULL AND u.active=1 AND ul.liked_id='.$userId);

		$likes = self::$db->loadObjectList();
		if (!count($likes)) {
			return false;
		}

		$template = self::$template;
		
		$body = '<h3>'. Text::_sprintf('user.notification.welcome', $user->username) .'</h3>';
		//$body .= '<p>'.Text::_sprintf('user.notification.likes', count($likes)).'</p>';
		$body .= '<p>'.Text::_('user.notification.likes_description').'</p>';
		$body .= '<p>';

		$like_notify = array();
		foreach ($likes as $key => $like) {
			$tnbl = Format::getThumbnail($like->profile, '120x120');
			$body .= '<img src="'.(Format::getProtocol().self::$domain.$tnbl['url']).'" width="120" style="border-radius: 10px; margin: 5px;" title="'.stripslashes($like->username).'" alt="'.($like->username).'">&nbsp;&nbsp;';
			$like_notify[] = $like->like_id;
		}
		$body .= '</p>';

		self::$db->setQuery('UPDATE user_likes SET notify=1 WHERE id IN ('.implode(',', $like_notify).')');
		self::$db->query();


		$subject = Text::_sprintf('user.notification.likes', count($likes));
		$body = str_replace('{{BODY}}', $body, $template);

		return self::send($subject, $body, $user->email);
	}

	private static function getNewUsersBySearch($userId = null, $opts = array()) {
		if (!$userId)return false;
		if (!in_array('new_user', $opts)) {
			return false;
		}
		if (!self::$db)self::$db = DB::getInstance();
		$user = self::getUser($userId);
		if (!$user) {
			return false;
		}

		$template = self::$template;
		$body = '<h3>'. Text::_sprintf('user.notification.welcome', $user->username) .'</h3>';
		$body .= '<p>'.Text::_('user.notification.search').'</p>';
		
		self::$db->setQuery('SELECT search, name FROM user_dating_search WHERE user_id='.$userId);
		$searchStrArr = self::$db->loadObjectList();

		// MIKOR KAPOTT UTOLJÁRA ILYEN ÉRTESÍTÉST?
		self::$db->setQuery('SELECT last_sent FROM users_nwl_settings JOIN users ON users.id=user_id WHERE users.active=1 AND user_id='.$userId);
		$last_sent = self::$db->loadResult();
		if (is_null($last_sent))return false;

		$ct = 0;
		foreach ($searchStrArr as $key => $searchArr) {
			if (!$searchArr)continue;
			$searchStr = $searchArr->search;
			$title = $searchArr->name;

			$search = array();
			parse_str($searchStr, $search);
			
			$result = self::searchUsers($userId, $search['search'], $last_sent);
			/*
			print '<pre>';
			print_r($result);
			print '</pre>';
			continue;
			*/
			if (!$result)continue;
			
			$body .= '<p>'.Text::_sprintf('user.notification.search_name', $title).'</p>';
			$body .= '<p>';
			foreach ($result as $key => $u) {
				$tnbl = Format::getThumbnail($u->profile, '120x120');
				$body .= '<img src="'.(Format::getProtocol().self::$domain.$tnbl['url']).'" width="120" style="border-radius: 10px; margin: 5px;" title="'.stripslashes($u->username).'" alt="'.($u->username).'">&nbsp;&nbsp;';
				$ct++;
			}
			$body .= '</p>';
		}

		$subject = Text::_('user.notification.search_title');
		$body = str_replace('{{BODY}}', $body, $template);
		
		if (!$ct)return false;

		return self::send($subject, $body, $user->email);
	}

	private static function searchUsers($userId = null, $search = array(), $last_sent = null) {
		
		if (!self::$db)self::$db = DB::getInstance();
		
		$except = array('location', 'age', 'more', 'distance');
		foreach ($search as $key => $value) {
			if (!in_array($key, $except)) {
				$$key = $value;
			}
		}

		$me = $userId;
		$q = 'SELECT profile, username, user_id FROM users u JOIN users_dating_datas ud ON user_id=u.id '.
			(isset($speak_lang) && is_array($speak_lang)?(' LEFT JOIN users_dating_datas_langs udd ON u.id=udd.user_id'):'').
			(isset($spirit_interest) && is_array($spirit_interest)?(' LEFT JOIN users_dating_datas_spir_int udsi ON u.id=udsi.user_id'):'').
			(isset($not_seen) && $not_seen==1?(' LEFT JOIN (SELECT COUNT(udt_id) AS see_count, visited_id FROM users_dating_views WHERE user_id = '.(int)$me.' GROUP BY visited_id) udv ON (udv.visited_id=u.id)'):'').
			' WHERE role < 4 AND active > 0 AND u.id !='.(int)$me;
		
		if (isset($gender) && is_array($gender) && $search['more']==1) {
			$q .= ' AND gender IN ('.implode(', ', $gender).')';
		} 

		if (isset($zodiac) && is_array($zodiac) && $search['more']==1) {
			$q .= ' AND zodiac IN ('.implode(', ', $zodiac).')';
		}
		
		if (isset($chinese_zodiac) && is_array($chinese_zodiac) && $search['more']==1) {
			$q .= ' AND ch_zodiac IN ('.implode(', ', $chinese_zodiac).')';
		}

		if (isset($ascendent_zodiac) && is_array($ascendent_zodiac) && $search['more']==1) {
			$q .= ' AND ascendent IN ('.implode(', ', $ascendent_zodiac).')';
		}

		if (isset($smoke) && is_array($smoke) && $search['more']==1) {
			$q .= ' AND smoke IN ('.implode(', ', $smoke).')';
		}

		if (isset($drink) && is_array($drink) && $search['more']==1) {
			$q .= ' AND drink IN ('.implode(', ', $drink).')';
		}

		if (isset($family_status) && is_array($family_status) && $search['more']==1) {
			$q .= ' AND family_status IN ('.implode(', ', $family_status).')';
		}

		if (isset($interest) && is_array($interest) && $search['more']==1) {
			$q .= ' AND interest IN ('.implode(', ', $interest).')';
		}

		if (isset($education) && is_array($education) && $search['more']==1) {
			$q .= ' AND education IN ('.implode(', ', $education).')';
		}

		if (isset($weight_from) && (int)$weight_from>0 && $search['more']==1) {
			$q .= ' AND weight >= '.(int)$weight_from;
		}

		if (isset($weight_to) && (int)$weight_to>0 && $search['more']==1) {
			$q .= ' AND weight <= '.(int)$weight_to;
		}

		if (isset($height_from) && (int)$height_from>0 && $search['more']==1) {
			$q .= ' AND height >= '.(int)$height_from;
		}

		if (isset($height_to) && (int)$height_to>0 && $search['more']==1) {
			$q .= ' AND height <= '.(int)$height_to;
		}

		if (isset($physique) && is_array($physique) && $search['more']==1) {
			$q .= ' AND physique IN ('.implode(', ', $physique).')';
		}

		if (isset($hair_color) && is_array($hair_color) && $search['more']==1) {
			$q .= ' AND hair_color IN ('.implode(', ', $hair_color).')';
		}

		if (isset($hair_style) && is_array($hair_style) && $search['more']==1) {
			$q .= ' AND hair_style IN ('.implode(', ', $hair_style).')';
		}

		if (isset($eye_color) && is_array($eye_color) && $search['more']==1) {
			$q .= ' AND eye_color IN ('.implode(', ', $eye_color).')';
		}

		if (isset($tempering) && is_array($tempering) && $search['more']==1) {
			$q .= ' AND tempering IN ('.implode(', ', $tempering).')';
		}

		if (isset($speak_lang) && is_array($speak_lang) && $search['more']==1) {
			$q .= ' AND lang_code IN ('.implode(', ', $speak_lang).')';
		}

		if (isset($looking_for) && is_array($looking_for) && $search['more']==1) {
			$q .= ' AND search IN ('.implode(', ', $looking_for).')';
		}

		if (isset($child) && is_array($child) && $search['more']==1) {
			$q .= ' AND child IN ('.implode(', ', $child).')';
		}

		if (isset($wish_child) && is_array($wish_child) && $search['more']==1) {
			$q .= ' AND wish_child IN ('.implode(', ', $wish_child).')';
		}

		if (isset($religion) && is_array($religion) && $search['more']==1) {
			$q .= ' AND religion IN ('.implode(', ', $religion).')';
		}

		if (isset($spirit_interest) && is_array($spirit_interest) && $search['more']==1) {
			$q .= ' AND spirit_interest IN ('.implode(', ', $spirit_interest).')';
		}

		if (isset($spirit_all_day) && is_array($spirit_all_day) && $search['more']==1) {
			$q .= ' AND spirit_all_day IN ('.implode(', ', $spirit_all_day).')';
		}

		if (isset($green) && is_array($green) && $search['more']==1) {
			$q .= ' AND green IN ('.implode(', ', $green).')';
		}

		if (isset($nutrition) && is_array($nutrition) && $search['more']==1) {
			$q .= ' AND green IN ('.implode(', ', $nutrition).')';
		}

		if (isset($not_seen) && $not_seen==1) {
			$q .= ' AND see_count IS NULL';
		}

		if (isset($has_about_me) && $has_about_me==1) {
			$q .= ' AND has_about_me = 1';
		}

		if (isset($search['location']) && mb_strlen($search['location'], Easy::charset) > 1	) {
			$search['distance'] = !isset($search['distance']) || $search['distance'] == 'extact' ? 0 : $search['distance'];
			if ($search['distance'] != 'all') {
				$cities = "('".implode("','", self::getNearestTowns($search['location'], (int)$search['distance'] ))."')";
				$q .= ' AND address IN '.$cities;
			}
		}

		if (isset($search['age_from']) && isset($search['age_to'])) {
			$from = date('Y-m-d 00:00:00', mktime(0,0,0,date('m'),date('d')-1,date('Y')-1-(int)$search['age_from']));
			$to = date('Y-m-d 00:00:00', mktime(0,0,0,date('m'),date('d'),date('Y')-(int)$search['age_to']));
			$q .= ' AND (birth BETWEEN '.self::$db->quote($to).' AND '.self::$db->quote($from).')';
		}

		if (isset($search['text']) && mb_strlen(Format::makeSafe($search['text']), Easy::charset)>2) {
			$text = Format::makeSafe($search['text']);
			$q .= ' AND username LIKE "%'.$text.'%"';
		}
		
		$q .= ' AND regdate >= '.self::$db->quote($last_sent);
		
		$q .= ' GROUP BY u.id';

		// 1: Regisztráció szerint
		// 2: ABC sorrend
		// 3: Utolsó belépés
		if (isset($search['order'])) {
			switch ((int)$search['order']) {
				default:
				case 1:
					$q .= ' ORDER BY regdate DESC, username ASC';
					break;
				case 2:
					$q .= ' ORDER BY username ASC, regdate DESC';
					break;
				case 3:
					$q .= ' ORDER BY u.last_login DESC, username ASC';
					break;
			}
		} else {
			$q .= ' ORDER BY regdate DESC, username ASC';
		}

		$q .= ' LIMIT 10 ';

		self::$db->setQuery($q);
		$list = self::$db->loadObjectList();

		return $list;
	}

	private static function getNearestTowns($city = null, $distance = 20) {
		if (!self::$db)self::$db = DB::getInstance();
		if (!$city)return array();
		self::$db->setQuery('SELECT cit_name, cit_lat, cit_long FROM __city WHERE cit_name LIKE '.self::$db->quote($city));
		$obj = self::$db->loadObject();
		if (!$obj) {
			return array();
		}

		$lat = self::$db->quote($obj->cit_lat);
		$long = self::$db->quote($obj->cit_long);

		self::$db->setQuery(
			'SELECT cit_name, ( 3959 * acos( cos( radians('.$lat.') ) 
				* cos( radians( cit_lat ) ) 
				* cos( radians( cit_long ) - radians('.$long.') ) 
				+ sin( radians('.$lat.') ) 
				* sin( radians( cit_lat ) ) ) ) AS distance
				FROM __city WHERE 1 
				GROUP BY cit_name having distance < '.$distance.' ORDER BY distance');

		$list = self::$db->loadObjectList();
		if (!$list)return array();
		$result = array();
		foreach ($list as $key => $city) {
			$result[$city->distance] = $city->cit_name;
		}
		$result[] = $obj->cit_name;

		return $result;
	}

	private static function getNewEventsNear($userId = null, $opts = array()) {
		if (!$userId)return false;
		if (!in_array('new_event_near', $opts)) {
			return false;
		}
		if (!self::$db)self::$db = DB::getInstance();
		$user = self::getUser($userId);
		self::$db->setQuery('SELECT address FROM users_dating_datas WHERE user_id='.$userId);
		$city = self::$db->loadResult();
		if (!$city) {
			return false;
		}

		$towns = self::getNearestTowns($city, 50);
		$cities = "('".implode("','", $towns)."')";

		$one_day = date('Y-m-d H:i:s', time() + 3600*24);
		self::$db->setQuery('SELECT * FROM users_dating_events WHERE city IN '.$cities.' AND (deleted IS NULL OR deleted="0000-00-00 00:00:00") AND time_start >= '.self::$db->quote($one_day). ' AND enabled = 1 AND user_id!='.$userId);
		$list = self::$db->loadObjectList();
		
		$template = self::$template;
		$body = self::getEventBody($list, $user);
		if (!is_array($body) || $body == false) {
			return false;
		}

		$like_notify = array();
		
		$subject = Text::_sprintf('user.notification.events_near', $body['count']);
		$body = str_replace('{{BODY}}', $body['html'], $template);

		return self::send($subject, $body, $user->email);
	}

	private static function getEventsModified($userId = null, $opts = array()) {
		if (!$userId)return false;
		if (!in_array('modify_event', $opts)) {
			return false;
		}
		if (!self::$db)self::$db = DB::getInstance();
		$user = self::getUser($userId);
		
		self::$db->setQuery('SELECT last_sent FROM users_nwl_settings JOIN users ON users.id=user_id WHERE users.active=1 AND user_id='.$userId);
		$last_sent = self::$db->loadResult();
		if (is_null($last_sent)) {
			$last_sent = time()-3600*24;
		} else {
			$last_sent = strtotime($last_sent) > 0 ? strtotime($last_sent) : (time()-3600*24);
		}

		self::$db->setQuery('SELECT * FROM users_dating_events ude JOIN users_dating_event_regs uder ON uder.event_id=ude.event_id WHERE (ude.deleted IS NULL OR ude.deleted="0000-00-00 00:00:00") AND ude.enabled=1 AND uder.user_id='.$userId.' AND ude.user_id!='.$userId.' AND ude.time_start>NOW() AND ude.time_modify>'.self::$db->quote(date('Y-m-d H:i:s', $last_sent)));
		$list = self::$db->loadObjectList();

		$template = self::$template;
		$body = self::getEventBody($list, $user, 'modify');
		if (!is_array($body) || $body == false) {
			return false;
		}

		$subject = Text::_sprintf('user.notification.events_modify', $body['count']);
		$body = str_replace('{{BODY}}', $body['html'], $template);

		return self::send($subject, $body, $user->email);
	}

	private static function getEventsDeleted($userId = null, $opts = array()) {
		if (!$userId)return false;
		if (!in_array('modify_event', $opts)) {
			return false;
		}
		if (!self::$db)self::$db = DB::getInstance();
		$user = self::getUser($userId);
		
		self::$db->setQuery('SELECT last_sent FROM users_nwl_settings JOIN users ON users.id=user_id WHERE users.active=1 AND user_id='.$userId);
		$last_sent = self::$db->loadResult();
		if (is_null($last_sent)) {
			$last_sent = time()-3600*24;
		} else {
			$last_sent = strtotime($last_sent) > 0 ? strtotime($last_sent) : (time()-3600*24);
		}

		self::$db->setQuery('SELECT * FROM users_dating_events ude JOIN users_dating_event_regs uder ON uder.event_id=ude.event_id WHERE ude.enabled=1 AND uder.user_id='.$userId.' AND ude.time_start>NOW() AND ude.user_id!='.$userId.' AND ude.deleted>'.self::$db->quote(date('Y-m-d H:i:s', $last_sent)));
		$list = self::$db->loadObjectList();

		$template = self::$template;
		$body = self::getEventBody($list, $user, 'deleted');
		if (!is_array($body) || $body == false) {
			return false;
		}

		$subject = Text::_sprintf('user.notification.events_deleted', $body['count']);
		$body = str_replace('{{BODY}}', $body['html'], $template);

		return self::send($subject, $body, $user->email);
	}

	private static function getEventsNotify($userId = null, $opts = array()) {
		if (!$userId)return false;
		if (!in_array('notify_event', $opts)) {
			return false;
		}
		if (!self::$db)self::$db = DB::getInstance();
		$user = self::getUser($userId);
		
		self::$db->setQuery('SELECT last_sent FROM users_nwl_settings JOIN users ON users.id=user_id WHERE users.active=1 AND user_id='.$userId);
		$last_sent = self::$db->loadResult();
		if (is_null($last_sent)) {
			$last_sent = time();
		} else {
			$last_sent = strtotime($last_sent) > 0 ? strtotime($last_sent) : (time()-3600*24);
		}

		if ($last_sent > time()-3600*24) {
			return false;
		}

		$start = date('Y-m-d 00:00:00', strtotime($start)+24*3600);
		$end = date('Y-m-d H:i:s', strtotime($start)+48*3600);

		self::$db->setQuery('SELECT * FROM users_dating_events ude JOIN users_dating_event_regs uder ON uder.event_id=ude.event_id WHERE (ude.deleted IS NULL OR ude.deleted="0000-00-00 00:00:00") AND ude.enabled=1 AND uder.user_id='.$userId.' AND (ude.time_start BETWEEN '.self::$db->quote($start).' AND '.self::$db->quote($end).') ');
		$list = self::$db->loadObjectList();
		if (!$list) {
			return false;
		}
		
		$template = self::$template;
		$body = self::getEventBody($list, $user, 'notify');
		if (!is_array($body) || $body == false) {
			return false;
		}

		$subject = Text::_sprintf('user.notification.events_notify', $body['count']);
		$body = str_replace('{{BODY}}', $body['html'], $template);

		return self::send($subject, $body, $user->email);
	}

	private static function getEventNewRegs($userId = null, $opts = array()) {
		if (!$userId)return false;
		if (!in_array('new_event_reg', $opts)) {
			return false;
		}
		if (!self::$db)self::$db = DB::getInstance();
		$user = self::getUser($userId);
		
		self::$db->setQuery('SELECT last_sent FROM users_nwl_settings JOIN users ON users.id=user_id WHERE users.active=1 AND user_id='.$userId);
		$last_sent = self::$db->loadResult();
		if (is_null($last_sent)) {
			$last_sent = time()-3600*24;
		} else {
			$last_sent = strtotime($last_sent) > 0 ? strtotime($last_sent) : (time()-3600*24);
		}
		

		self::$db->setQuery('SELECT * FROM users_dating_events ude WHERE (ude.deleted IS NULL OR ude.deleted="0000-00-00 00:00:00") AND ude.enabled=1 AND ude.user_id='.$userId.' AND time_start>NOW()');
		$list = self::$db->loadObjectList();
		
		$template = self::$template;
		$body = '<h3>'. Text::_sprintf('user.notification.welcome', $user->username) .'</h3>';
		$body .= '<p>'.Text::_('user.notification.event_regs_description').'</p>';
		$body .= '<div>';
			
		$OK = 0;
		foreach ($list as $key => $event) {
			self::$db->setQuery('SELECT * FROM users_dating_event_regs uder JOIN users u ON u.id=uder.user_id JOIN users_dating_datas udd ON udd.user_id=u.id WHERE uder.event_id='.(int)$event->event_id.' AND uder.user_id!='.(int)$userId.' AND uder.time_registered>'.self::$db->quote(date('Y-m-d H:i:s', $last_sent)));
			$regs = self::$db->loadObjectList();
			
			if (!$regs || count($regs) == 0) continue;

			if (!self::chechEventNotification($userId, $event->event_id, 'register')) {
				continue;
			}

			$body .= '<strong>Esemény: '.stripslashes($event->title).'</strong><br>';
			$body .= '<p>';
			foreach ($regs as $key => $reg) {
				$tnbl = Format::getThumbnail($reg->profile, '120x120');
				$body .= '<img src="'.(Format::getProtocol().self::$domain.$tnbl['url']).'" width="120" style="border-radius: 10px; margin: 5px;" title="'.stripslashes($reg->username).'" alt="'.($reg->username).'">&nbsp;&nbsp;';
				$OK++;
			}
			$body .= '</p>';

		}
		$body .= '</div>';
		
		if ($OK == 0) {
			return false;
		}

		$subject = Text::_sprintf('user.notification.event_regs', $OK);
		$body = str_replace('{{BODY}}', $body, $template);

		return self::send($subject, $body, $user->email);

	}


	private static function getEventBody($list, $user, $type = 'near') {
		if (!$list) return false;

		$OK = 0;

		$body = '<h3>'. Text::_sprintf('user.notification.welcome', $user->username) .'</h3>';
		$body .= '<p>'.Text::_('user.notification.events_'.$type.'_description').'</p>';
		$body .= '<ul style="list-style: none; padding: 0; margin: 0;">';
		foreach ($list as $key => $event) {
			if (!self::chechEventNotification($user->id, $event->event_id, $type)) {
				continue;
			}
			$body .= '
					<li style="padding: 0; margin: 0;">
						<h3 style="color: #2DB064;">'.stripslashes($event->title).'</h3>
						<div><strong>'.$event->city.', '.$event->address.' / '.strftime('%Y. %B %d %H:%M', strtotime($event->time_start)).(strtotime($event->time_end)>0 ? (' - '.strftime('%Y. %B %d %H:%M', strtotime($event->time_end))) : '').'</strong></div>
					</li>';
			$OK++;
			//<p>'.stripslashes($event->description).'</p>
		}

		$body .= '</ul>';

		if ($OK == 0) {
			return false;
		}

		return array('html' => $body, 'count' => $OK);
	}

	private static function chechEventNotification($userId = null, $eventId = null, $type = 'near') {
		if (!$userId || !$eventId)return false;
		if (!self::$db)self::$db = DB::getInstance();
		self::$db->setQuery('SELECT last_sent FROM users_dating_events_notify WHERE type='.self::$db->quote($type).' AND event_id='.$eventId.' AND user_id='.$userId);
		$last_sent = self::$db->loadResult();
		
		if (is_null($last_sent)) {
			// INSERT
			self::$db->setQuery('INSERT INTO users_dating_events_notify (user_id, event_id, `type`, last_sent) VALUES ('.$userId.', '.$eventId.', '.self::$db->quote($type).', NOW());');
			if (!self::$db->query()) {
				return false;
			}

			return true;
		} else {

			if ($type == 'near') {
				return false;
			}
			
			$last_sent = strtotime($last_sent);

			self::$db->setQuery('SELECT frequency FROM users_nwl_settings WHERE user_id='.$userId);
			$setting = self::$db->loadObject();

			switch ($setting->frequency) {
				default:
				case 1:
					// ALWAYS
					$lastday = time() - 300; // 5 MINUTES
					if ($last_sent < $lastday) {
						self::$db->setQuery('UPDATE users_dating_events_notify SET last_sent=NOW() WHERE type='.self::$db->quote($type).' AND user_id='.$userId.' AND event_id='.$eventId);
						self::$db->query();
						return true;
					}
					break;
				case 2:
					$lastday = time() - 3600 * 24;
					if ($last_sent < $lastday) {
						self::$db->setQuery('UPDATE users_dating_events_notify SET last_sent=NOW() WHERE type='.self::$db->quote($type).' AND user_id='.$userId.' AND event_id='.$eventId);
						self::$db->query();
						return true;
					}
					break;
				case 3:
					$lastday = time() - 3600 * 24 * 7;
					if ($last_sent < $lastday) {
						self::$db->setQuery('UPDATE users_dating_events_notify SET last_sent=NOW() WHERE type='.self::$db->quote($type).' AND user_id='.$userId.' AND event_id='.$eventId);
						self::$db->query();
						return true;
					}
					break;
				case 4:
					$lastday = time() - 3600 * 24 * 30;
					if ($last_sent < $lastday) {
						self::$db->setQuery('UPDATE users_dating_events_notify SET last_sent=NOW() WHERE type='.self::$db->quote($type).' AND user_id='.$userId.' AND event_id='.$eventId);
						self::$db->query();
						return true;
					}
					break;
			}

			return false;
		}

		return false;
	}

	private static function getUser($userId = null) {
		if (!$userId)return false;
		if (!self::$db)self::$db = DB::getInstance();
		self::$db->setQuery('SELECT * FROM users WHERE active=1 AND id='.$userId);
		return self::$db->loadObject();
	}

	private static function send($subject, $body, $to, $mailer) {
		$mailer = Mailer::create();
		$mailer->clearAllRecipients();
		$mailer->AddAddress($to);
		$mailer->Subject = $subject;
		$mailer->isHTML();
		$mailer->Body = $body;

		try {
			if ($mailer->Send()) {
				return true;
			} else {
				return false;
			}
		} catch (Exception $e) {
			$mailer->smtpReset();
		} catch (PHPMailerException $e) {
			$mailer->smtpReset();
		}

		if ($mailer->Send()) {
			return true;
		} else {
			return false;
		}
	}

}