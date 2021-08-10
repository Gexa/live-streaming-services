<?php defined('__GX__') or die('Access denied!');

class Template extends EObject {

	CONST VERSION = '3.0.0';
	
	protected $name = null;
	protected $JS = Array();
	protected $JSText = Array();
	protected $CSS = Array();
	protected $CSSText = Array();

	protected $content = null;

	public function __construct() {
		$this -> name = Easy::template;
	}

	function addJS($fileName, $dir = '', $fullurl = false, $theme = '') {

		if (!$fullurl) {
			if (!strlen($dir) > 0) {
				$js_url = URL_BASE . 'templates/' . $this -> name . '/themes/'.($theme=='' ? (Easy::$Theme) : $theme).'/js/' . $fileName;
			} else {
				$js_url = URL_BASE . 'templates/' . $dir . '/themes/'.($theme=='' ? (Easy::$Theme) : $theme).'/js/' . $fileName;
			}
			if (!in_array($js_url, $this->JS))
				$this -> JS[] = $js_url;
		} else {
			if (!in_array($fileName, $this->JS)) {
				$this -> JS[] = $fileName;
			}
		}
	}

	function addJSText($string) {
		$this -> JSText[] = $string;
	}

	function loadScripts() {
		$scripts = '';

		if (count($this -> JS) > 0) {
			foreach ($this->JS AS $val) {
				$scripts .= '<script src="' . $val . '" type="text/javascript"></script>' . "\r\n";
			}
		}

		if (count($this -> JSText) > 0) {
			foreach ($this->JSText AS $val) {
				$scripts .= $val . "\r\n";
			}
		}
		return $scripts;
	}

	function addCSS($fileName, $dir = '', $theme = false, $url = false) {

		if ($url != false) {
			$css_url = $fileName;
		} elseif (!strlen($dir) > 0) {
			$css_url = URL_BASE . 'templates/' . $this -> name . '/themes/'.($theme==false ? Easy::$Theme : $theme).'/css/' . $fileName;
		} else {
			$css_url = URL_BASE . 'templates/' . $dir . '/themes/'.($theme==false ? Easy::$Theme : $theme).'/css/' . $fileName;
		}
		if (!in_array($css_url, $this->CSS)) {
			$this -> CSS[] = $css_url;
		}
	}

	function addCSSText($string) {
		$this -> CSSText[] = $string;
	}

	function loadCSS() {
		//$css = '';
		$cssPath = TEMP . DS . 'cache';
		if (!is_dir($cssPath)) {
			@mkdir($cssPath, 0755, true);
			@chmod($cssPath, 0755);
		}

		$css = '';
		$cssContent = '';

		//$cssFileName = 'build'.(defined('_ADMIN_')?'_admin' : '').(Easy::$MobileView?'_mobile':'');
		//$cssFile = $cssPath . DS . $cssFileName.'.css';
		//$cssContent = '@charset "UTF-8";'."\n";
		if (count($this -> CSS) > 0) {
			foreach ($this->CSS AS $val) {
				$css .= '<link href="' . $val . '" type="text/css" rel="stylesheet" media="all">' . "\n";
				//$cssContent .= '@import url('.$val .');' . "\n";
			}
		}
		if (count($this -> CSSText) > 0) {
			foreach ($this->CSSText AS $val) {
				$cssContent .= "\r\n\r\n /* BUILD INLINE CSS */ \r\n";
				$cssContent .= strip_tags($val). "\r\n";
			}
		}

		//file_put_contents($cssFile, $cssContent);

		//$css = '<link href="' . URL_BASE. 'tmp/cache/'.$cssFileName.'.css?v='.rand(0,9999). '" type="text/css" rel="stylesheet" media="all">' . "\n";
		return $css . $cssContent;
	}

	function getContent() {
		return $this -> content;
	}

	public static function getUrl($is_mobile = true) {
		return URL_BASE.'templates/'.(!defined('_ADMIN_') ? Easy::template : Easy::template_admin).'/themes/'.(!$is_mobile ? str_replace('_mobile', '', Easy::$Theme) : Easy::$Theme);
	}

}
?>
