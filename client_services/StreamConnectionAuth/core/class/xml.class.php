<?php

defined('__GX__') or die('Access denied!');

/**
 * Class to generate and parse XML
 *
 * @author Gergo Boldogh
 */
class XML extends EObject {

	public $ctid;
	public $limit;
	public $portal = 1;
	public $portalData;
	private $db = null;

	public function __construct() {

		$this -> db = DB::getInstance();
		$this -> db -> setQuery('SELECT * FROM portal WHERE id=' . (int)$this -> portal);
		$this -> portalData = $this -> db -> loadObject();
	}

	public function setParams($ctid, $limit) {
		$this -> ctid = $ctid;
		$this -> limit = $limit;
	}

	public function getXMLPath($limit, $language = 'hu_HU') {

		if (!$this -> ctid or $this -> ctid == null)
			return false;

		$dir = TEMP . DS . 'XML' . DS;
		if (!file_exists($dir))
			mkdir($dir, 0755, true);

		if (isset($limit) && (int)$limit != 0)
			return $dir . $language . '.' . $this -> ctid . '_' . $limit . '.xml';
		else
			return $dir . $language . '.' . $this -> ctid . '.xml';
	}

	protected function getFreshNews($offset = 0, $limit = 100, $language) {
		$this -> db -> setQuery('	
		  SELECT *, title, stext, n.options AS nOptions, ct.options AS cOptions, n.language AS language 
		  FROM contact c 
		    LEFT JOIN news n ON (n.id=c.newsid)
		    LEFT JOIN category ct ON (ct.id = n.main_category) 
			LEFT JOIN category_group cg ON (ct.group_id=cg.group_id)
		  WHERE 
		    ct.published = 1 AND 
		    newsdate <= ' . $this -> db -> quote(date('Y-m-d H:i:s')) . ' AND
		    n.language = ' . $this -> db -> quote($language) . '
		  GROUP BY newsid 
		  ORDER BY newsdate DESC, title ASC 
		  LIMIT ' . $offset . ', ' . $limit);

		$result = $this -> db -> loadAssocList();

		if (!$result or !count($result) > 0)
			return false;

		return $result;
	}

	protected function getCategoryNews($offset = 0, $limit = 100, $language) {
		if (!(int)$this -> ctid > 0)
			return false;

		$this -> db -> setQuery('	
		  SELECT *, title, stext, n.options AS nOptions, ct.options AS cOptions, n.language AS language 
		  FROM contact c 
		    LEFT JOIN news n ON (n.id=c.newsid)
		    LEFT JOIN category ct ON (ct.id = n.main_category) 
			LEFT JOIN category_group cg ON (ct.group_id=cg.group_id)
		  WHERE 
		    ct.published = 1 AND 
		    c.ctid=' . (int)$this -> ctid . ' AND 
		    newsdate <= ' . $this -> db -> quote(date('Y-m-d H:i:s')) . ' AND
		    n.language = ' . $this -> db -> quote($language) . ' 
		  GROUP BY newsid 
		  ORDER BY newsdate DESC, title ASC 
		  LIMIT ' . $offset . ', ' . $limit);

		$result = $this -> db -> loadAssocList();

		if (!$result or !count($result) > 0)
			return false;

		return $result;
	}

	public function XMLGenerate($offset = 0, $limit = 10, $allFresh = false, $language = 'hu_HU') {

		$this -> limt = $limit;

		$updateXML = true;
		$xmlFile = $this -> getXMLPath($limit, $language);

		if (!$this -> validateFileTime($xmlFile))
			$updateXML = false;

		if ($updateXML) {

			if (!$allFresh)
				$newsArr = $this -> getCategoryNews($offset, $limit, $language);
			else
				$newsArr = $this -> getFreshNews($offset, $limit, $language);

			if (!$newsArr OR !count($newsArr) > 0)
				return false;

			$xml = new DOMDocument('1.0', 'UTF-8');
			$rss = $xml->createElement("rss"); 
			$xml -> appendChild($rss); 
			$version = $xml->createAttribute("version"); 
			$rss -> appendChild($version); 
			$versionValue = $xml->createTextNode("2.0"); 
			$version->appendChild($versionValue); 
			
			//create channel element 
			$root = $xml->createElement("channel"); 
			/********** Create Static Section *******************/ 

			//create static information 
			$Stitle =  $xml->createElement("title"); 
			$Slink = $xml->createElement("link"); 
			$Sdesc = $xml->createElement("description"); 
			$language = $xml->createElement("language"); 
			$SpubDate = $xml->createElement("pubdate"); 
			$Sauthor = $xml->createElement("author"); 
			$webmaster = $xml->createElement("webmaster"); 
			
			//create text nodes for static info 
			$StitleText = $xml->createTextNode(Easy::ProjectName); 
			$SlinkText = $xml->createTextNode("http://".$_SERVER['HTTP_HOST'].URL_BASE); 
			$SdescText = $xml->createTextNode(''); 
			$languageText = $xml->createTextNode(str_replace('_', '-', strtolower(Easy::$Language))); 
			$SpubDateText = $xml->createTextNode(date('Y-m-d H:i')); 
			$SauthorText = $xml->createTextNode(Easy::owner_email); 
			$webmasterText = $xml->createTextNode(Easy::owner_email); 
			
			//add text to static elements 
			$Stitle->appendChild($StitleText); 
			$Slink->appendChild($SlinkText); 
			$Sdesc->appendChild($SdescText); 
			$language->appendChild($languageText); 
			$SpubDate->appendChild($SpubDateText); 
			$Sauthor->appendChild($SauthorText); 
			$webmaster->appendChild($webmasterText); 
			
			//append static items to channel 
			$root->appendChild($Stitle); 
			$root->appendChild($Slink); 
			$root->appendChild($Sdesc); 
			$root->appendChild($language); 
			$root->appendChild($SpubDate); 
			$root->appendChild($Sauthor); 
			$root->appendChild($webmaster); 
			
			foreach ($newsArr AS $k => $v) {
			
				$node = $xml -> createElement('item');
				$node -> setAttribute('date', $v['newsdate']);
				$node -> setAttribute('id', $v['newsid']);

				$options = unserialize($v['nOptions']);

				if (trim($options['small_image']) != '') {
					$small_image = Format::getThumbnail($options['small_image']);
					$options['small_image'] = $small_image['url'];
				}

				$options = serialize($options);

				$node -> appendChild($url = $xml -> createElement('portalurl', 'http://' . $this -> portalData -> main_domain));
				$url -> setAttribute('id', $this -> portal);

				$node -> appendChild($title = $xml -> createElement('title'));
				$title -> appendChild($xml -> createCDATASection($v['title']));

				$node -> appendChild($category = $xml -> createElement('category'));
				$category -> appendChild($xml -> createCDATASection($v['catname']));
				$category -> setAttribute('id', $v['ctid']);

				$node -> appendChild($stext = $xml -> createElement('description'));
				$stext -> appendChild($xml -> createCDATASection($v['stext']));

				$node -> appendChild($is_event = $xml -> createElement('is_event'));
				$is_event -> setAttribute('enabled', $v['is_event']);
				$is_event -> appendChild($eventdate = $xml -> createElement('eventdate'));
				$eventdate -> appendChild($xml -> createCDATASection($v['eventdate']));

				$is_event -> appendChild($eventdateTo = $xml -> createElement('eventdateTo'));
				$eventdateTo -> appendChild($xml -> createCDATASection($v['eventdateTo']));

				$lng = $v['language'];
				$controller = isset($v['controller']) && ($v['controller']!='') ? $v['controller'] : 'news';
				$url = Router::_($controller.'->show', 'id='.$v['newsid']. ( isset($v['url_params']) && $v['url_params']!='' ? ('&'.$v['url_params']) : '' ) , $lng);

				$node -> appendChild( $url_child = $xml -> createElement('link'));
				$url_child -> appendChild( $xml -> createCDATASection($url) );
				
				$node -> appendChild($params = $xml -> createElement('params'));
				$params -> appendChild($xml -> createCDATASection($options));

				$root -> appendChild($node);
			}
			
			$rss -> appendChild($root);
			$xml -> formatOutput = true;

			$filename = $xmlFile;
			$xml -> save($filename);
		}

		return $xmlFile;
	}

	private function logMemoryUsage() {
		//Log::_log("Used " . (memory_get_peak_usage() / 1024 / 1024) . " MB\n", 'xml_memory');
	}

	function get_data($url) {
		if (!function_exists('curl_init')) {

			$_dir = TEMP . DS . 'XML' . DS;
			$xmlName = md5(trim($url)) . '.xml';
			$fileTime = $this -> validateFileTime($_dir.$xmlName);
			if ($fileTime != false) {
				return file_get_contents($url); 
			} else {
				return file_get_contents($_dir.$xmlName);
			}
		}

		$ch = curl_init();
		$timeout = 1;
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
		$data = curl_exec($ch);
		curl_close($ch);
		return $data;
	}

	private function XMLParser($xmlFile) {

		if (!strlen($xmlFile) > 0) {
			throw new Exception('No file to parse!', 1);
		}

		$xml = new DOMDocument('1.0', 'UTF-8');

		$_dir = TEMP . DS . 'XML' . DS;
		$xmlName = md5(trim($xmlFile)) . '.xml';
		
		if (!file_exists($_dir . $xmlName)) {
			//file_put_contents(dirname(__FILE__).DS.'xml.txt', $_dir.$xmlName);
			$xmlData = $this -> get_data($xmlFile);
		} else {
			$xmlData = file_get_contents($_dir.$xmlName);
		}
		
		//if (Easy::debug != 0)
		//	EasyLog::_log($xmlFile, 'xml_data');

		if (!$xml -> loadXML($xmlData))
			if (!$xml -> load($xmlFile))
				return array();
		
		$doc = $xml -> documentElement;
		$channel = $xml -> getElementsByTagName('news');
		if (!$channel) $channel = $xml -> getElementsByTagName('item');
		
		$result = Array();
		foreach ($channel AS $item) {
		
			$ID = $item -> getAttribute('id');
			$date = $item -> getAttribute('date');
			$portalurl = $item -> getElementsByTagName("portalurl") -> item(0) -> nodeValue;
			$title = $item -> getElementsByTagName("title") -> item(0) -> nodeValue;
			$category = $item -> getElementsByTagName("category") -> item(0) -> nodeValue;
			$stext = $item -> getElementsByTagName("stext") -> item(0) -> nodeValue;

			/*$is_event = $item -> getElementsByTagName("is_event") -> item(0);
			
			$eventdate = $is_event -> getElementsByTagName("eventdate") -> item(0) -> nodeValue;
			$eventdateTo = $is_event -> getElementsByTagName("eventdateTo") -> item(0) -> nodeValue;
			
			$is_event = $is_event -> getAttribute('enabled');
			*/
			
			
			$params = $item -> getElementsByTagName("params") -> item(0) -> nodeValue;
			$opts = unserialize($params);
			if (isset($opts['category_name']))
				$url = '/' . $opts['category_name'] . '/' . Format::_alias($title);
			else $url = $item -> getElementsByTagName("url") -> item(0) -> nodeValue;
			
			$images = Array( 
				'small' => $item -> getElementsByTagName("images") -> item(0) -> getElementsByTagName('small') -> item(0) -> getAttribute('src'),
				'medium' => $item -> getElementsByTagName("images") -> item(0) -> getElementsByTagName('original') -> item(0) -> getAttribute('src'),
				'original' => $item -> getElementsByTagName("images") -> item(0) -> getElementsByTagName('original') -> item(0) -> getAttribute('src')
			);

			$result[strtotime($date)][(int)$ID] = 
				Array(
					'id' => $ID, 
					'newsdate' => $date, 
					'portalurl' => $portalurl, 
					'title' => $title, 
					'stext' => $stext, 
					'category' => $category, 
					'url' => $url, 
					'is_event' => isset($is_event) ? $is_event : false, 
					'eventdate' => isset($eventdate) ? $eventdate : null, 
					'eventdateTo' => isset($eventdateTo) ? $eventdateTo : null, 
					'params' => (strlen($params) > 5) ? unserialize($params) : Array(), 
					'images' => $images
				);
		}
		if (!count($result) > 0)
			return false;

		ksort($result);

		return $result;
	}

	public static function validateFileTime($filepath) {

		$filetime = !file_exists($filepath) ? 0 : filemtime($filepath);
		$checkTime = time() - 60 * 60 * 3;
		/*
		var_dump($filepath);
		var_dump(date('Y-m-d H:i:s', filemtime($filepath)));
		var_dump(date('Y-m-d H:i:s', time()-3600 ));
		*/
		if ($filetime < $checkTime)
			return true;

		return false;
	}

	/** Object private cache function * */
	private function _cache($files, $data = null, $arrayName = 'merged') {

		$path = TEMP . DS . 'cache' . DS;
		if (!is_dir($path))
			@mkdir($path, 0755, true);

		$fname = $path . '__' . md5(implode('-', $files)) . '__';
		
		$timeCheck = self::validateFileTime($fname);
		
		if ((!file_exists($fname) && $data != null) or ($data != null && $timeCheck)) {

			$nl = "\r\n";
			$_c = '<?php ' . $nl;
			$_c .= '	$' . $arrayName . ' = ' . var_export($data, true);
			$_c .= '?>' . $nl;

			if (!is_file($fname) or !file_exists($fname))
				touch($fname, 0755);
			
			file_put_contents($fname, $_c);
		}

		if (file_exists($fname))
			@include ($fname);

		if (!isset($merged) or !is_array($merged))
			return false;

		return $merged;
	}

	/** Merge XML files ordered by newsdate * */
	public function Merge() {

		$files = Array();
		for ($i = 0; $i < func_num_args(); $i++) {

			if (!is_array(func_get_arg($i)))
				$files[] = func_get_arg($i);
			else {
				$files = func_get_arg($i);
			}
		}

		$path = TEMP . DS . 'cache' . DS;//dirname(__FILE__)
		$fname = $path . '__' . md5(implode('-', $files)) . '__';
		$timeCheck = self::validateFileTime($fname);
		$merged = $this -> _cache($files);

		if (is_array($merged) && !$timeCheck)
			return $merged;

		$merged = Array();
		for ($i = 0; $i < func_num_args(); $i++) {
			$arg = func_get_arg($i);
			if (!is_array($arg)) {
				$merged = $merged + $this -> XMLParser($arg);
			} else {
				foreach ($arg AS $v) {
					$xmlItem = $this -> XMLParser($v);
					if (!is_array($xmlItem))
						$xmlItem = Array();
					if (!is_array($merged))
						$merged = Array();
					
					$merged = $merged + $xmlItem;
				}
			}
		}

		if (!count($merged))
			return Array();

		krsort(array_unique($merged), SORT_STRING);
		$this -> _cache($files, $merged);

		return $merged;
	}

	public function listNewsFromXML($xmlData, $category_options, $offset, $limit) {
		$news = Array();
		$i = 0;
		foreach ($xmlData AS $key => $data) {
			foreach ($data AS $_d) {
				if ($i >= $offset && $i < $offset + $limit) {
					$item = new stdClass();
					
					$_d['params'] = !is_array($_d['params']) ? unserialize($_d['params']) : $_d['params'];
					$_d['params']['small_image'] = $_d['images']['small'];
					$options = (is_array($_d['params'])) ? serialize($_d['params']) : $_d['params'];

					$item -> title = $_d['title'];
					$item -> catname = $_d['category'];
					$item -> stext = $_d['stext'];
					$item -> nOptions = $options;
					$item -> newsid = $_d['id'];
					$item -> newsdate = $_d['newsdate'];
					$item -> options = serialize($category_options);
					$item -> is_event = $_d['is_event'];
					$item -> eventdate = $_d['eventdate'];
					$item -> eventdateTo = $_d['eventdateTo'];
					$item -> portalurl = $_d['portalurl'];
					$item -> url = $_d['url'];
					
					array_push($news, $item);
				}
				$i++;
			}
		}
		
		return $news;
	}

	public function countNewsFromXML($xmlData) {
		$news = Array();
		$i = 0;
		foreach ($xmlData AS $key => $data) {
			$i += count($data);
		}
		return $i;
	}

	public static function createArray($string) {

		$path = TEMP . DS . '_temp_' . DS;
		if (!is_dir($path))
			@mkdir($path, 0777, true);

		$fname = md5($string);

		if (!(strlen(trim($string)) > 0))
			$string = 'Array()';
		else
			$string = base64_decode($string);

		$nl = "\r\n";
		$PHP = '
	    <?php' . $nl . '
		  $XMLArr = ' . $string . $nl . '; 
	    ?>';

		file_put_contents($path . $fname . '.php', $PHP);

		require_once ($path . $fname . '.php');

		$array = $XMLArr;
		unlink($path . $fname . '.php');

		return $array;
	}

}
?>
