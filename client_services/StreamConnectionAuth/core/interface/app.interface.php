<?php defined('__GX__') or die('Access denied!');

interface AppInterface {
	public function init();
	public function render();
	public static function getCtrl();
	public static function getAction();
	public static function getTpl();
	public static function getModuls();
	public static function getLanguages();
	public static function getLanguageCode($shortcut = 'hu');
	public static function getLanguageName($code = 'hu_HU');
	public static function getUser();
	public static function show404ErrorPage($message = '');
	public static function setMeta($data = array());
	public static function _settings($id = 0);
}