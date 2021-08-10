<?php defined('__GX__') or die('Access denied!');

interface DBInterface {
	public function setQuery($sql, $offset = 0, $limit = 0);
	public function getQuery();
	public function query();
	public function loadResult();
	public function loadResultArray($numinarray = 0);
	public function loadAssocList($key = '');
	public function loadObject($class = 'stdClass');
	public function loadObjectList($key = '', $class = 'stdClass');
	public function insertid();
}