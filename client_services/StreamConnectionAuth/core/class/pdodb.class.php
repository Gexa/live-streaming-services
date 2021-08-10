<?php defined('__GX__') or die('Access denied!');

class PDODB extends EObject {

	private $_resource = null;
	private $_cursor = null;
	private $_prefix = null;
	private $_sql = null;
	private $_params = null;

	public function __construct($prefix = '') {

		$this->_prefix = $prefix;

		$dsn = 'mysql:host='.Easy::DBHost.';dbname='.Easy::DBName;
		$options = array(
			PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
			); 
		
		if (!$this -> _resource) {
			$this -> _resource = new PDO($dsn, Easy::DBUser, Easy::DBPass, $options);
		}

		return $this->_resource;
	}

	public function quote($str) {
		return $this->_resource->quote($str);
	}

	public function getQuery() {
		return $this->_sql;
	}

	public function setQuery($sql = '', $params = array()) {
		$this->_sql = $sql;
		$this->_params = $params;
	}

	public function query($sql = false, $params = array()) {
		$sql = str_replace('#__', $this->_prefix, !$sql ? $this->_sql : $sql);
		$this->_cursor = $this->_resource->prepare($sql);
		$this->_cursor->execute(!empty($params) ? $params : $this->_params);
		return $this->_cursor;
	}

	public function loadObjectList() {
		if (!$this->_cursor) {
			return null;
		}
		return $this->_cursor->fetchAll(PDO::FETCH_OBJ);
	}

	public function loadAssocList() {
		if (!$this->_cursor) {
			return null;
		}
		return $this->_cursor->fetchAll(PDO::FETCH_ASSOC);
	}

	public function loadObject() {
		if (!$this->_cursor) {
			return null;
		}
		return $this->_cursor->fetchObject('stdClass');
	}

	public function loadResult() {
		if (!$this->_cursor) {
			$this->_cursor = $this->query();
		}
		$ret = 0;
		if ($d = $this->_cursor->fetch()) {
			$ret = $d[0];
		}
		return $ret;
	}

	public function insertid() {
		if (!$this->_cursor) {
			return null;
		}
		return $this->_resource->lastInsertId();
	}


}