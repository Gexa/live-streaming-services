<?php defined('__GX__') or die('Access denied!');

class Navigation {

	CONST VERSION = '3.0.0';

	public $offset = 0;
	public $limit = 10;
	public $total = 0;
	private $_getdata = null;

	function __construct($total = 0, $offset, $limit, $get = false) {
		if ((int)$total == 0)
			return false;

		$this -> _getdata = $get;

		$this -> total = $total;

		if (!$offset > 0 or (int)$offset < 0)
			$offset = $this -> offset;
		if (!$limit > 0 or (int)$limit < 0)
			$limit = $this -> limit;

		$this -> limit = $limit;
		$this -> offset = $offset;
	}

	private function getPreRequest() {
		if ($this -> _getdata != false) {

			$preReq = '?';
			$req = array_merge($_GET, $_POST);
			if (count($req) > 0) {
				$pre = '?';
				foreach ($req AS $key => $value) {
					if ($key != 'limit' && $key != 'offset')
						if (!is_array($value))
							$preReq .= $key . '=' . $value . '&';
						else {
							foreach ($value AS $k => $v) {
								$preReq .= $key . '[' . $k . ']' . '=' . $v . '&';
							}
						}
				}

			}
		} else {

			$_params = Array();
			$_reserved = Array('controller', 'action', 'id', 'limit', 'offset');
			if (isset($req) && is_array($req))
				foreach ($req AS $key => $value) {
					if (!in_array($key, $_reserved)) {
						$_params[$key] = $value;
					}
				}

			
			$preReq = $_SERVER['REQUEST_URI'];
			$pattern = '/(\?.*)/si';

			$x = preg_replace($pattern, '', $preReq);

			$_c = 0;
			foreach ($_params AS $k => $v) {
				if (!is_array($v)) {
					if ($_c != 0)
						$x .= '&';
					else
						$x .= '?';

					$x .= $k . '=' . $v;
				} else {
					foreach ($v AS $_k => $a) {
						if ($_c != 0)
							$x .= '&';
						else
							$x .= '?';
						$x .= $k . '[' . $_k . ']=' . $a;
						$_c++;
					}
				}
				$_c++;
			}

			if (count($_params) > 0)
				$x .= '&';
			else
				$x .= '?';
			$preReq = $x;

		}

		return $preReq;
	}

	/** Admin navigation in Easy Core v.2.1 **/
	function getNavigation_v1($ID = '', $displayPages = 5) {

		$pages = ceil($this -> total / $this -> limit);

		$actual_page = ceil($this -> offset / $this -> limit);

		if (!$pages or $pages == 1)
			return '';

		$dp = $displayPages;

		if ($pages <= $dp) {
			$from = 0;
			$to = $pages;
		} else {
			$from = ($actual_page - 2 >= 0) ? $actual_page - 2 : 0;
			$to = ($actual_page + 2 < $pages) ? ($actual_page + 3) : $pages;
			if ($to < $dp)
				$to += $dp - $to;
		}

		$html = '
	    <ul class="navigation" id=' . $ID . '>';

		$preReq = $this -> getPreRequest();

		if ($this -> offset > 0) {
			$html .= '
		<li class="nav-first">' . '<a href="' . $preReq . 'limit=' . $this -> limit . '&offset=0">&laquo;</a>' . '</li>
		<li class="nav-prev">
			<a href="' . $preReq . 'limit=' . $this -> limit . '&offset=' . ($this -> offset - $this -> limit) . '">&lt;</a>
		</li>';
		}

		for ($i = $from; $i < $to; $i++) {
			$offset = ($i * $this -> limit);

			if ($offset == $this -> offset)
				$active = 'current';
			else
				$active = '';
			$html .= '
		<li>
		    <a href="' . $preReq . 'limit=' . $this -> limit . '&offset=' . $offset . '" class="' . $active . '">' . ($i + 1) . '</a>
		</li>';
		}

		if (ceil(($this -> offset + 1) / $this -> limit) < $pages)
			$html .= '
		<li class="nav-next">
			<a href="' . $preReq . 'limit=' . $this -> limit . '&offset=' . ($this -> offset + $this -> limit) . '">&gt;</a>
		</li>
		<li class="nav-last">
			<a href="' . $preReq . 'limit=' . $this -> limit . '&offset=' . (($pages - 1) * $this -> limit) . '">&raquo;</a>
		</li>
		';

		$html .= '
	    </ul>';

		return $html;
	}

	/** SEO-friendy site navigation in Easy Core v.2.1 **/
	function getNavigation($ID = 'easy-navigation', $displayPages = 5) {
		
		if ($displayPages < 3)
			$displayPages = 3;
		
		if (defined('_ADMIN_'))
			return $this -> getNavigation_v1($ID, $displayPages);

		$state = isset($_REQUEST['state']) ? ((int)$_REQUEST['state']['page'] - 1) : false;

		$url = Router::getUrl();
		
		$preReq = '';
		$request_vars = array_merge($_GET, $_POST);
		foreach ($request_vars AS $key => $value) {
			if ($key != 'limit' && $key != 'offset')
				if (!is_array($value))
					$preReq .= $key . '=' . $value . '&';
				else {
					foreach ($value AS $k => $v) {
						if (!is_array($v)) {
							$preReq .= $key . '[' . $k . ']' . '=' . $v . '&';
						} else {
							foreach ($v as $kv => $vv) {
								$preReq .= $key . '[' . $k . '][]' . '=' . $vv . '&';
							}
						}
					}
				}
		}
		
		$pages = ceil($this -> total / $this -> limit);
		$actual_page = ceil($this -> offset / $this -> limit);

		if (!$pages or $pages == 1)
			return '';

		$preReq = ((strlen($preReq) > 0) ? ('?' . $preReq) : '');

		$html = '
	    <aside><ul class="pagination" id=' . $ID . '>';

		if ($this -> offset > 0) {
			//<li class="nav-first">' . '<a href="' . $url . '/1/' . $preReq . '"><i class="fa fa-chevron-left"></i></a>' . '</li>
			$html .= '
				<li class="nav-prev">
					<a href="' . $url . '/' . ((($this -> offset - $this -> limit) / $this -> limit) + 1) . '/' . $preReq . '"><i class="fa fa-chevron-left"></i></a>
				</li>';
		}

		$dp = $displayPages;
		
		if ($pages <= $dp) {
			$from = 0;
			$to = $pages;
		} else {
			$from = ($actual_page - floor($displayPages / 2) >= 0) ? ($actual_page - floor($displayPages / 2) + (($pages == $actual_page + 1) ? -1 : 0)) : 0;
			$to = ($actual_page + floor($displayPages / 2) < $pages) ? ($actual_page + floor($displayPages / 2) + 1) : $pages;
			if ($to < $dp)
				$to += $dp - $to;
		}
		
		for ($i = $from; $i < $to; $i++) {
			$offset = ($i * $this -> limit);

			//var_dump($offset);
			
			if ($offset == $this -> offset)
				$active = 'active';
			else
				$active = '';
			$html .= '
				<li>
					<a href="' . $url . '/' . ($offset / $this -> limit + 1) . '/' . $preReq . '" class="' . $active . '">' . ($i + 1) . '</a>
				</li>';
		}

		if (ceil(($this -> offset + 1) / $this -> limit) < $pages)
			$html .= '
				<li class="nav-next">
					<a href="' . $url . '/' . (($this -> offset + $this -> limit) / $this -> limit + 1) . '/' . $preReq . '"><i class="fa fa-chevron-right"></i></a>
				</li>
				';
				/*<li class="nav-last">
					<a href="' . $url . '/' . ((($pages - 1) * $this -> limit) / $this -> limit + 1) . '/' . $preReq . '"><i class="fa fa-chevron-right"></i></a>
				</li>*/

		$html .= '
	    </ul></aside>';

		return $html;

	}

	function getOffset() {
		return $this -> offset;
	}

	function getLimit() {
		return $this -> limit;
	}

}
?>