<?php defined('__GX__') or die('Access denied!');

abstract class ModelBase {
	
	CONST VERSION = '3.0.0';
		
	public function __construct() {
		$this->db = DB::getInstance();
		$access = new Access();
		$this->access = $access->getRoles(); // User access rules
		$this->user = User::getInstance();
	}
	
}