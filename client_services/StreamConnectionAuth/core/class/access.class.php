<?php defined('__GX__') or die('Access denied!');

/*
roles => {
	'access' => [1,2,3,4,5,6,7,8,9,10,100],
	'category' => [],
	'group' => [],
}
*/

class Access extends EObject {

	CONST VERSION = '3.0.0';

	private $user = null;
	private $db = null;
	protected $data = null;

	function __construct($id = null) {

		$this->user = App::getUser();
		$this->db = DB::getInstance();

		if ($this->user->loggedIn() && !$id) {
			if ($this->user->getData() != null) {
				$this->db->setQuery('SELECT * FROM user_roles WHERE user_id='.$this->user->getData()->id);
				$this->data = $this->db->loadObject();
			} else {
				$this->data = null;
			}
		} elseif ((int)$id > 0 && $this->user->loggedIn()) {
			$this->db->setQuery('SELECT * FROM user_roles WHERE user_id='.(int)$id);
			$this->data = $this->db->loadObject();
		} else {
			$this->data = null;
		}
	}

	function getRoles() {
		if (!is_object($this->data)) {
			return false;
		}
		$d = json_decode($this->data->roles);
		return $d;
	}

	function getLanguages() {
		if (!is_object($this->data)) {
			return array();
		}
		$l = json_decode($this->data->language);
		return $l;
	}

	function getAccessRules() {
		$r = $this->getRoles();
	}

}