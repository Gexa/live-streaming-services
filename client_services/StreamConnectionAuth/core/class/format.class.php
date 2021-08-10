<?php defined('__GX__') or die('ACCESS DENIED!');

class Format {

	CONST VERSION = '3.0.0';

	public static function html2txt($html) {
		if (!is_array($html)) {
			return stripslashes(strip_tags(html_entity_decode($html, ENT_COMPAT, 'UTF-8')));
		} else {
			foreach ($html as $key => $value) {
				$html[$key] = stripslashes(strip_tags(html_entity_decode($value, ENT_COMPAT, 'UTF-8')));
			}

			return $html;
		}
	}

	public static function strCut($str, $limit = null) {
		mb_internal_encoding('UTF-8');
		$s = Easy::$_settings;
		if (!$limit)
			$desc_length = $s -> short_desc_length;
		else
			$desc_length = (int)$limit;

		return mb_substr($str, 0, $desc_length, 'UTF-8') . ((mb_strlen($str, 'UTF-8')>$desc_length) ?  '...' : '');
	}

	public static function money($number, $decimals = 0) {
		return number_format((int)$number, $decimals, ',', ' ');
	}

	public static function getProtocol() {
		return 'http'.((isset($_SERVER['HTTPS'] )  && $_SERVER['HTTPS'] != 'off')?'s':'').'://';
	}

	/**
	 * create date format like 2013. May 25.
	 * @param $timestr timestamp|date string
	 * @return string Formatted date
	 */
	public static function dateTime($timestr) {
		
		mb_internal_encoding(Easy::charset);
		//setlocale(LC_ALL, Easy::$Language.'.'.Easy::charset);
		
		$time = (!is_int($timestr)) ? strtotime($timestr) : $timestr;
		return strftime('%Y. %B %d. %H:%m', $time);
	} 

	public static function _alias($string) {

		$invalid = array(
			'Š' => 'S', 'š' => 's', 'Ð' => 'Dj', 'Ž' => 'Z', 'ž' => 'z', 'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Æ' => 'A', 'Ç' => 'C', 'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I', 'Ñ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ő' => 'O', 'Ø' => 'O', 'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ű' => 'U', 'Ý' => 'Y', 'Þ' => 'B', 'ß' => 'Ss', 'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'a', 'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ð' => 'o', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ő' => 'o', 'ö' => 'o', 'ø' => 'o', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ű' => 'u', 'ü' => 'u', 'ý' => 'y', 'ý' => 'y', 'þ' => 'b', 'ÿ' => 'y', 'ƒ' => 'f', '\''=>'', '"'=>'', '.' => '', ','=>'', '!'=>'');
		
		$cyr  = array('а','б','в','г','д','e','ё','ж','з','и','й','к','л','м','н','о','п','р','с','т','у', 
            'ф','х','ц','ч','ш','щ','ъ', 'ы','ь', 'э', 'ю','я','А','Б','В','Г','Д','Е','Ж','З','И','Й','К','Л','М','Н','О','П','Р','С','Т','У',
            'Ф','Х','Ц','Ч','Ш','Щ','Ъ', 'Ы','Ь', 'Э', 'Ю','Я' );
            $lat = array( 'a','b','v','g','d','e','io','zh','z','i','y','k','l','m','n','o','p','r','s','t','u',
            'f' ,'h' ,'ts' ,'ch','sh' ,'sht' ,'a', 'i', 'y', 'e' ,'yu' ,'ya','A','B','V','G','D','E','Zh',
            'Z','I','Y','K','L','M','N','O','P','R','S','T','U',
            'F' ,'H' ,'Ts' ,'Ch','Sh' ,'Sht' ,'A' ,'Y' ,'Yu' ,'Ya' );
		
        $string=str_replace(' ', '-', str_replace($cyr, $lat, $string));
		$string = str_replace(array_keys($invalid), array_values($invalid), $string);
		$string = preg_replace('/[^a-zA-Z0-9-\']/u', '-', $string);
		
		while(strpos($string, '--')) {
			$string = str_replace('--', '-', $string);
		}
		return strtolower(rtrim( ($string), '-' ));
		
	}
	
	public static function simpleHTML($string) {
		
		return strip_tags($string, '<strong><i><u><b>');
		
	}

	public static function getThumbnail($filename, $size = '140x140', $crop = false) {
		$filename = urldecode($filename);
		if (strpos($filename, 'http://') !== false) {
			
			$url_path = dirname($filename);
			$filename = $url_path . '/thumbs/'.$size.'_'. basename($filename);
			
			return Array('url' => $filename);
		
		}
		
		if ($filename[0] != '/')
			if (strlen(URL_BASE) > 1)
				$filename = str_replace(URL_BASE, '', '/' . $filename);
			else
				$filename = URL_BASE . $filename;


		if (!is_file(BASE_PATH . $filename) && !is_file($filename))
			return Array('url' => '');

		$fn = str_replace('/', DS, $filename);
		$fPath = BASE_PATH . DS . $fn;

		$info = pathinfo($fPath);
		$_dir = $info['dirname'] . DS . 'thumbs';
		
		//var_dump($fPath);
		
		if (!is_dir($_dir))
			@mkdir($_dir, 0777, true);

		$new_fname = $size . '_' . $info['basename'];

		if ($new_fname[0] == DS or $new_fname[0] == '/')
			$newPath = $_dir . $new_fname;
		else
			$newPath = $_dir . DS . $new_fname;
		
		if (is_file($newPath) && file_exists($newPath) && (1000 * 60 * 60) <  time() - filemtime($newPath)) {
			unlink($newPath);
		}
		
		if (!file_exists($newPath) or !is_file($newPath)) {

			switch ($info['extension']) {
				case 'jpg' :
				case 'jpeg' :
				default :
					$image = imagecreatefromjpeg($fPath);
					break;
				case 'png' :
					$image = imagecreatefrompng($fPath);
					break;
				case 'gif' :
					$image = imagecreatefromgif($fPath);
					break;
				case 'bmp' :
					$image = imagecreatefromwbmp($fPath);
					break;
			}

			$x = imagesx($image);
			$y = imagesy($image);
			$sizeArr = explode('x', $size);
			if ($x > $y) {
				$new_x = (int)$sizeArr[0];
				if (!$crop)
					$new_y = $y / $x * (int)$sizeArr[0];
				else
					$new_y = (int)$sizeArr[1];
			} else if ($y > $x) {
				$new_y = (int)$sizeArr[0];
				if (!$crop)
					$new_x = $x / $y * (int)$sizeArr[0];
				else
					$new_x = (int)$sizeArr[1];
			} else if ($y == $x) {
				$new_x = $new_y = (int)$sizeArr[0];
			}

			$tnbl = imagecreatetruecolor($new_x, $new_y);
			$img = imagecopyresampled($tnbl, $image, 0, 0, 0, 0, $new_x, $new_y, $x, $y);

			/*switch ($info['extension']) {
			 case 'jpg':
			 case 'jpeg':
			 default:
			 imagejpeg($tnbl, $newPath, 90);
			 break;
			 case 'png':
			 imagepng($tnbl, $newPath, 90);
			 break;
			 case 'gif':
			 imagegif($tnbl, $newPath, 90);
			 break;
			 case 'bmp':
			 imagewbmp($tnbl, $newPath, 90);
			 break;
			 }*/
			$newPath = str_replace($info['extension'], 'jpg', $newPath);
			imagejpeg($tnbl, $newPath, 100);
		}

		$url = str_replace(array(BASE_PATH.DS, BASE_PATH), '', $newPath);
		/*var_dump(URL_BASE);
		die;*/

		return Array('path' => str_replace(Array(DS . DS, DS . DS . DS), DS, $newPath), 
					'url' => str_replace(Array('///', '//'), '/', (((URL_BASE != '/') ? URL_BASE : '') . str_replace(Array(DS), Array('/'), $url)))
					);
	}

	public static function makeSafe($string) {
		if (!is_string($string))return $string;
		return addslashes(trim(self::html2txt($string)));
	}
	
	public static function validateUrlStatus($url, $type = 1) {
		
		if (function_exists('curl_init')) {
				
			$handle = curl_init($url);
			curl_setopt($handle,  CURLOPT_RETURNTRANSFER, TRUE);
			
			/* Get the HTML or whatever is linked in $url. */
			$response = curl_exec($handle);
			
			/* Check for 404 (file not found). */
			$httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
			if($httpCode == 404) {
				return false;
			}
			
			curl_close($handle);
			return true;
			
		} else {
			
			$info = parse_url($url);
			
			$chk = checkdnsrr($info['host']);
			if (!$chk) return false;
			
			$headers = get_headers($url, $type);
			
			if ($headers[0] == 'HTTP/1.1 200 OK') {
				return true;
			} elseif ($headers[0] == 'HTTP/1.1 404 Not Found') {
				return false;
			}
		}
	}
	
	public static function validateEmail($email) {
		if (!$email)
			return false;

		if (function_exists('filter_var')) {
			return filter_var($email, FILTER_VALIDATE_EMAIL);
		} elseif (preg_match("/^([a-zA-Z0-9])+([a-zA-Z0-9\._-])*@([a-zA-Z0-9_-])+([a-zA-Z0-9\._-]+)+$/", $email)) {
			list($username, $domain) = explode('@', $email);
			if (!checkdnsrr($domain, 'MX')) {
				return false;
			}
			return true;
		}
	}

	public static function _($str, $return = false) {
		if (!$return)
			print '<pre>'.$str.'</pre>'."\r\n";
		else
			return '<pre>'.$str.'</pre>'."\r\n";
	}
}
