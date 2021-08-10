<?php defined ( '__GX__' ) or die ( 'ACCESS DENIED!' );

class Menu {

	CONST VERSION = '3.0.0';
	private static $id = null;

	/**
	 * Get Menu structure by MainMenu id
	 *
	 * @param int $id MainMenu ID
	 * @param string $type menu or menu_contact or category (category list)
	 * @param bool $addRoot - Add mainPage link DEFAULT true
	 */
	public static function get($id = null, $type = 'menu', $addRoot = true, $caching = false) {
		
		$caching = Easy::caching && $caching;
		self::$id = $id;
		
		if ($caching) {

			phpFastCache::$path = PRIVATE_STORAGE . DS . 'cache' . DS;
			phpFastCache::$storage = 'files';

			$cache = new phpFastCache('files');			
			
			$keyword_menu = md5($id.$type.(Easy::$MobileView?'_mobile':''));
			$html = $cache -> get($keyword_menu);
		}

		if (isset($html) && !is_null($html) && $caching) 
			return $html;

		$db = DB::getInstance();

		$name = ($type != 'menu' && $type != 'menu_contact') ? 'catname AS name' : 'name';

		if ($id != '' && !is_int($id)) {
			if (Easy::languageControl) {
				$db -> setQuery('SELECT id FROM menu WHERE name=' . $db -> quote($id) . ' AND language=' . $db -> quote(Easy::$Language));
				$mid = $db -> loadResult();
			} else {
				$db -> setQuery('SELECT id FROM menu WHERE name=' . $db -> quote($id));
				$mid = $db -> loadResult();
			}
		} elseif (is_int($id)) {
			$mid = $id;
		}

		if ($type != 'menu_contact') {
			$_q = 'SELECT ' . $name . ', id AS categoryId, parent_id, options FROM ' . $type . ' WHERE (language=\'*\' OR language=' . $db -> quote(Easy::$Language) . ') ORDER BY position ASC';
			$db -> setQuery($_q);
			$c = $db -> loadAssocList();
		} else {
			$db -> setQuery('SELECT * FROM menu_contact WHERE menu_id=' . (int)$mid . ' AND (language=\'*\' OR language=' . $db -> quote(Easy::$Language) . ') ORDER BY position ASC');
			$c = $db -> loadAssocList();
		}
		$menu = Menu::structCategories($c);
		$html = Menu::createMenuRecursive(0, $menu, '', $addRoot, 'category');
		
		if ($caching) {
			$cache->set($keyword_menu, $html, 3600 * 24);
		}
		
		return $html;
	}

  	///////////// MENÜRENDSZER TÖMB ÖSSZÁLLÍTÁSA //////////////////
  	/**
  	* Használat Menu::structCategories
  	* 
  	* @param Array $categories
  	* @return Array $tree (sub-structured category Array)
  	*/
  	static function structCategories(&$categories) {
  		$new = array();
  		foreach ($categories as $key => $a) {
  			if ((int)$a['parent_id']>0)
  				$new[$a['parent_id']][] = $a;
  		}

  		foreach ($categories AS $k1 => $v1) {
  			if ($v1['parent_id']==0)
  				$tree[$k1] = self::createTree($new, array($categories[$k1]));
  		}
  		if (isset($tree))
  			return $tree;

  		return Array();
  	}

  	private static function createTree(&$list, $parent, $level = 0){
  		$tree = array();
  		foreach ($parent as $k=>$l){
  			$id = $l['categoryId'];
  			if (isset($list[$id])) 
  			{
  				$l['children'] =  self::createTree($list, $list[$id], $level+1);
  			}
  			if ($level == 0)
  				$tree = $l;
  			else
  				$tree[] = $l;

  		} 
  		return $tree;
  	}
    ///////////// MENÜRENDSZER TÖMB ÖSSZÁLLÍTÁS VÉGE //////////////


	///////////// MENÜRENDSZER FELÉPÍTÉSE /////////////////////////
	/**
	* Függvény hívása: Menu::createMenuRecursive(0, structCategories $tree, '');
	* 
	* @param int $level = 0
	* @param Array $categories
	* @param string $HTML
	*/
	public static function createMenuRecursive( $level, &$categories, $HTML, $addRoot = true, $type = 'category' ) {
		
		$CID =  0;
		$active = (int)Request::getVar('c_id', null);
		$return = '';
		$cts = $categories;
		$user = App::getUser();

		$addHTML = '';
		$subHTML = '';
		for ($i=0; $i<$level; $i++) {
			//$addHTML .= '<span class="sep"></span>';
			$subHTML .= ($i>=1)?'-sub':'sub';
		}
		
		$rel = defined('_ADMIN_') ? ('rel="'.(($level>0)?$cts[0]['parent_id']:0).'"') : '';
		$tmpHTML = '<ul '.((defined('_ADMIN_') && $level == 0) ? 'id="menu-editor"' : ($level == 0 ? ('id="'.strtolower(self::$id).'"') : '') ).' class="'.(($level>0)?$subHTML: ($type.'-list')).'" '.$rel.'>';

		if ($addRoot && $type=='category' && $level == 0)
			$tmpHTML .= '<li class="passive"><a href="'.Router::_('default->default', null).'"><span>'.Text::_('global.mainpage').'</span></a></li>';
		
		foreach ($cts as $key => $category) {
			
			$cid = $category['categoryId'];
			if ($type != 'menu') {
				
				$options = (isset($category['options']) && $category['options']!='' && $type!='shop_category') ? 
				(array)json_decode($category['options']) : 
				null;
				
				$access = isset($options['menu_access']) ? (int)$options['menu_access'] : 0;

				if (defined('_ADMIN_') || $access == 0 || ($access == 1 && $user->loggedIn()) || ($access == 2 && !$user->loggedIn()) || ($access == 3 && $user->loggedIn() && Users::isPremium($user->getData()->id))) {
					$url = 
					(isset($category['options']) && ($options['url']!='' OR $options['route']!='') ) 
					? (($options['url']!='') ? Router::URL($options['url']) : Router::_($options['route'], $options['params'])) 
					: ( !is_null($options) ?  Router::_($type.'->list', Array('id'=>$category['categoryId'])) : Router::_('webshop->show_category', Array('id'=>$category['categoryId'], 'group_id'=>Request::getVar('group_id', 0, 'INT'))) );
					
					$rel = (isset($options['follow']) && $options['follow']!='') ? $options['follow'] : '';
					$icon = (isset($options['icon']) && $options['icon']!='') ? ('<span class="icon '.$options['icon'].'"></span>') : '';

					$active = Request::getUrl() == $url ? ' active' : '';
					
					if (!Easy::$MobileView) {
						$tmpHTML .= 
						'<li class="' . $options['cssClass'] .$active. '">'.
						'<a href="'.$url.'" '. (($options['target']!='')?'target="'.$options['target'].'"': '') .' '.$rel.'>'.
							$icon.$addHTML.$category['name'].
						'</a>'.
						(isset($category['children']) ? 
							'{'.$cid.'}' : '');

					} else {
						$tmpHTML .= 
						'<li class="'. $options['cssClass'] .'">'.
						(isset($category['children'])  ? ('<span>'.$category['name'].'</span>') : 
														('<a href="'. $url .'" '.(($options['target']!='')?'target="_blank"':'') .' '.$rel .'>'.
															$icon.$addHTML.$category['name'].
														'</a>')).
						(isset($category['children'])  ? '<div>{'.$cid.'}</div>' : '');
					}

					if ( isset($category['children']) ) {
						$tmpHTML = self::createMenuRecursive($level+1, $category['children'], $tmpHTML, $addRoot, $type);
					}
				}

			} else {
				
				if (!defined('_ADMIN_')) {
					
					$options = ((isset($category['options']) && $category['options']!='')) ? unserialize($category['options']) : null;
					$url = 
					($options['url']!='' OR $options['route']!='' ) 
					? (($options['url']!='') ? Router::URL($options['url']) : Router::_($options['route'], $options['params'])) 
					: Router::_($type.'->list', Array('id'=>$category['categoryId']));
					
					$rel = (isset($options['follow']) && $options['follow']!='') ? $options['follow'] : '';

					$tmpHTML .= 
					'<li class="'. $options['cssClass'] .'">'.
					'<a href="'. $url .'" '.(($options['target']!='')?'target="_blank"':'') .' '.$rel .'>'.$addHTML.$category['name'].'</a>'.
					((isset($category['children']) ) ? '{'.$cid.'}' : '');
					
					if (isset($category['children'])) 
						$tmpHTML = self::createMenuRecursive($level+1, $category['children'], $tmpHTML, false, 'menu');
				} else {
					$tmpHTML .= 
					'<li rel="'.$category['id'].'" class="one-item" id="item_'.$category['id'].'">'.
					'<h4>'.
					'<a href="javascript:void(0)">'.$addHTML.$category['name'].'</a>'.
					'<span class="mbuilder-options float-left">'.
					'	<span class="icon fa fa-pencil" title="'.Text::_('admin.mbuilder.modify').'" onclick="EasyBuilder.modify('.$category['id'].');"></span>'.
					'	<span class="icon fa fa-trash" title="'.Text::_('admin.mbuilder.delete').'" onclick="EasyBuilder.del('.$category['id'].');"></span>'.
					'</span>'.
					'</h4>'.
					((isset($category['children'])) ? '{'.$cid.'}' : '<ul rel="'.$cid.'"></ul>');
					
					if (isset($category['children'])) 
						$tmpHTML = self::createMenuRecursive($level+1, $category['children'], $tmpHTML, false, 'menu');
				}
			}
			
			
		}
		$tmpHTML .= '</ul>';
		
		if ($level != 0) {
			$parent = $cts[0]['parent_id'];
			$return = str_replace('{'.$parent.'}', $tmpHTML, $HTML);
		} else {
			$return .= $tmpHTML;
		}
		
		return $return;
		
	}
    ///////////// MENÜRENDSZER FELÉPÍTÉS VÉGE /////////////////////

	public static function createShopMenuRecursive( $level, &$categories, $HTML ) {

		$addRoot = false;

		$return = '';
		$cts = $categories;
		
		$addHTML = '';
		$subHTML = '';
		for ($i=0; $i<$level; $i++) {
			$addHTML .= '<span class="sep"></span>';
			$subHTML .= ($i>=1)?'-sub':'sub';
		}
		
		$tmpHTML = '<ul '. (($level==0)?'id="shop_category_list"' : '').' class="'.(($level>0)?$subHTML: ('shop_category-list')).'">';
		
		foreach ($cts as $key => $category) {
			
			$cid = $category['categoryId'];		
			$url = !isset($category['url']) ? Router::_('webshop->show_category', Array('id'=>$category['categoryId'], 'group_id'=>Request::getVar('group_id', 0, 'INT'))) : $category['url'];
			
			$tmpHTML .= 
			'<li class="">'.
			'<a href="'.$url.'">'.$addHTML.$category['name'].'</a>'.
			(
				(isset($category['children'])) ? 
				'{'.$cid.'}' : 
				''
				);

			if ( isset($category['children']) ) {
				$tmpHTML = self::createShopMenuRecursive($level+1, $category['children'], $tmpHTML);
			}
		}
		$tmpHTML .= '</ul>';
		
		if ($level != 0) {
			$parent = $cts[0]['parent_id'];
			$return = str_replace('{'.$parent.'}', $tmpHTML, $HTML);
		} else {
			$return .= $tmpHTML;
		}
		
		return $return;
		
	}
    ///////////// MENÜRENDSZER FELÉPÍTÉS VÉGE /////////////////////

}