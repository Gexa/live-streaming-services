<?php defined('__GX__') or die('Access denied!');

class Block extends EObject {

	CONST VERSION = '3.0.0';
	private static $params;

	// Static function to show static boxes
	public static function show($blockName, $role = Array(), $language_dependent = false, $params = Array(), $caching = false) {

		self::$params = $params;
		
		$accessObj = new Access();
		$access = $accessObj->getRoles();
		if (!isset($caching) || is_null($caching)) {
			$caching = Easy::caching;
		}
		$enabled = true;
		if (count($role) > 0) {
			$user = User::getInstance();
			if (isset($role['access']) && $user -> getData() != false) {
				if (is_array($role['access']) && !is_array($access) && in_array($user -> getData() -> role, $role['access'])) {
					$enabled = true;
				} elseif (is_array($role['access']) && is_array($access)) {
					foreach($role['access'] AS $ac) {
						if (in_array($ac, $access)) {
							$enabled = true;
						}
					}
				} elseif (!is_array($role['access']) && $role['access'] == $user -> getData() -> role) {
					$enabled = true;
				} else {
					$enabled = false;
				}
			} else
				$enabled = false;
		}

		if ($language_dependent)
			$blockName = Easy::$Language . DS . strtolower($blockName);
		else
			$blockName = strtolower($blockName);

		if (!defined('_ADMIN_')) {
			$path = TEMPLATES . DS . Easy::template . DS . 'block' . DS . $blockName . '.block.php';
		} else {
			$path = TEMPLATES . DS . Easy::template_admin . DS .  'block' . DS . $blockName . '.block.php';
		}

		if (defined('_ADMIN_'))$caching = false;

		if (is_file($path) && $enabled) {
			if (!$caching) {
				ob_start();
					@include ($path);
					$html = ob_get_contents();
				ob_end_clean();
			} else {
				
				$cache = new phpFastCache('files');
				
				$keyword_block = md5($blockName . serialize($params). Easy::$Language . (Easy::$MobileView ? '_mobile' : ''));
				$html = $cache->get($keyword_block);

				if (is_null($html)) {
					ob_start();
						@include ($path);
						$html = ob_get_contents();
					ob_end_clean();
					$cache->set($keyword_block, $html, Easy::cache_time);
				}

			}
			return $html;
		} else {
			return '';
		}
	}
	
	
	public static function showcase($blockName, $role = Array(), $containsHTML = true, $params = Array()) {
		
		if (!$blockName)
			return false;
		
		// $params
		/* item_class: class of each slide items
		 * template: template of showcase
		 * */
		
		if (!isset($params['limit']))
			$params['limit'] = 5;
		
		
		if (!isset($params['template']) or $params['template'] == '') {
			$tpl = 'showcase.default.tpl';
		} else $tpl = $params['template'];
		
		
		$template = TEMPLATES . DS . Easy::template . DS . 'leads' . DS . $tpl;
		if (!is_file($template)) 
			return '';
		
		ob_start();
			include($template);
			$content = ob_get_contents();
		ob_end_clean();
		
		$accessObj = new Access();
		$access = $accessObj->getRoles();
		if (!isset($caching) || is_null($caching)) {
			$caching = Easy::caching;
		}

		$enabled = true;
		if (count($role) > 0) {
			$user = User::getInstance();
			if (isset($role['access']) && $user -> getData() != false) {
				if (is_array($role['access']) && !is_array($access) && in_array($user -> getData() -> role, $role['access'])) {
					$enabled = true;
				} elseif (is_array($role['access']) && is_array($access)) {
					foreach($role['access'] AS $ac) {
						if (in_array($ac, $access)) {
							$enabled = true;
						}
					}
				} elseif (!is_array($role['access']) && $role['access'] == $user -> getData() -> role) {
					$enabled = true;
				} else {
					$enabled = false;
				}
			} else
				$enabled = false;
		}
		
		if (!$enabled)
			return '';
		
		$language = Easy::languageControl ? Easy::$Language : Easy::defaultLanguage;
		
		$caching = Easy::caching;
		if ($caching) {
			$cache = new phpFastCache('files');
			$keyword_block = md5('showcase_'.$blockName.serialize($params).'_'.Easy::$Language. (Easy::$MobileView ? '_mobile' : ''));
			$html = $cache->get($keyword_block);
		} else $html = null;

		if (is_null($html) || !$caching) {

			$db = DB::getInstance();
			$db->setQuery('
				SELECT * FROM showcase 
					JOIN showcase_items ON (showcase_id = showcase.id) 
				WHERE 
					activate < now() AND 
					block_name = '.$db->quote($blockName). ' AND 
					(showcase.language = "*" OR showcase.language='.$db->quote($language). ')  
					ORDER BY position ASC 
					LIMIT 0, '.(int)$params['limit']);
			
			$showcase = $db->loadObjectList();
			
			if (!$showcase or !count($showcase)>0)
				return '';
			
			$matches = array();
			preg_match_all('/\{\{SLIDE\}\}+(.*)\{\{\/SLIDE\}\}+/si', $content, $matches);
			
			$leadTemplate = $matches[1];
			$leads = '';

			if (!isset($params['img_width']) && $params['img_width'] != '' && $params['img_width']) { $width = ' width="'.$params['img_width'].'"'; } else { $width = ''; }
			if (!isset($params['img_height']) && $params['img_height'] != '' && $params['img_height']) { $height = ' height="'.$params['img_height'].'"'; } else { $height = ''; }
			
			foreach ($showcase AS $k => $item) {
				
				if (!isset($item->image) or !$item->image) {
					$image = '';
					$image_url = '';
				} else {
					$image = '<img src="' . ((strpos('http://', $item->image) === false) ? URL_BASE : '' ). $item->image . '"  title="'.$item->title.'" alt="'.$item->alt.'"'.$width.$height.' />';
					$image_url = ((strpos('http://', $item->image) === false) ? URL_BASE : '' ). $item->image;
				}
				
				
				if ($containsHTML!=true) {
					$item->html = Format::strCut(Format::html2txt(stripslashes($item->html)), Easy::$_settings->short_desc_length);
				} else {
					$item->html = Format::simpleHTML(stripslashes($item->html));
				}
				
				$leads .= str_replace(Array('{{SLIDE_TITLE}}', '{{SLIDE_URL}}', '{{SLIDE_IMAGE}}', '{{SLIDE_IMAGE_URL}}', '{{SLIDE_HTML}}', '{{SLIDE_CLASS}}'), Array(stripslashes($item->name), $item->url, $image, $image_url, $item->html, (isset($params['item_class']) ? ('class="'.$params['item_class'].'"') : '')), $leadTemplate[0]);
			}
			
			$html = str_replace($matches[0], $leads, $content);
			
			if ($caching) {
				$cache -> set($keyword_block, $html, Easy::cache_time);
			}

		}
		
		return $html;
	}
	
	/** Get block from database **/
	private $_enabled = null;
	private $_role = null;
	public $data = null;
	private $db = null;
	private $content = null;

	public function __construct($block = null, $role = Array(), $return = false) {
		
		$this->_role = $role;
		
		if (!$block)
			return '';
		
		
		$this -> db = DB::getInstance();

		if (is_int($block)) {
			$q = 'SELECT * FROM blocks WHERE id=' . (int)$block. ' AND (language="*" OR language='.$this->db->quote(Easy::$Language).')';
		} elseif (is_string(Format::makeSafe($block))) {
			$q = 'SELECT * FROM blocks WHERE name=' . $this -> db -> quote(Format::makeSafe($block)). ' AND (language="*" OR language='.$this->db->quote(Easy::$Language).')';
		}


		$this -> db -> setQuery($q);
		$blockData = $this -> db -> loadObject();
		
		if (!is_object($blockData))
			return '';

		if (Easy::languageControl && $blockData -> language != Easy::$Language && $blockData -> language != '*')
			return '';
		
		$accessObj = new Access();
		$access = $accessObj->getRoles();
		
		$this -> _enabled = true;
		//$this -> _role = Array('access' => explode(',', $blockData -> role));
		$this -> data = $blockData;
		
		if (count($this -> _role) > 0) {
			$user = User::getInstance();

			if (isset($this -> _role['access']) && $user -> getData() != false) {
				
				if (is_array($this -> _role['access']) && !is_array($access) && in_array($user -> getData() -> role, $this -> _role['access'])) {
					$this -> _enabled = true;
				}  elseif (is_array($role['access']) && is_array($access)) {
					foreach($role['access'] AS $ac) {
						if (in_array($ac, $access)) {
							$this-> _enabled = true;
						}
					}
				} elseif (!is_array($this -> _role['access']) && $this -> _role['access'] == $user -> getData() -> role) {
					$this -> _enabled = true;
				} else {
					$this -> _enabled = false;
				}
			} else
				$this -> _enabled = true;
		}

		$path = TEMPLATES . DS . Easy::template . DS . 'block' . DS . 'html.block.php';
		if (is_file($path) && $this -> _enabled) {
			ob_start();
			@include ($path);
			$c = ob_get_contents();
			ob_end_clean();
			if (!$return)
				echo $c;
			else $this->content = $c;
		} else {
			if (!$return)
				echo '';
			else $this->content = '';
		}
	}

	public function get() {
		return $this->content;
	}

}
