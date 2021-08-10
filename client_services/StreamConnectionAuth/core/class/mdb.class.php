<?php defined('__GX__') or die('Access denied!');

class MDb extends EObject implements DBInterface {

	private $connection = null;
	private $collection = null;

	public function __construct($options = array()) {
		$connectData = array();
		if (isset($options['dbuser']) && isset($options['dbpass'])) {
			$connectData = Array(
				'username' => $options['dbuser'],
				'password' => $options['dbpass'],
				);
		}
		if (isset($options['dbname'])) {
			$connectData['db'] = $options['dbname'];
		}
		
		$this->connection = new MongoClient("mongodb://".$options['host'], $connectData);
	}

	public function getConnection() {
		return $this->connection;
	}
	
	public function findAsArray(array $params, array $fields = array(), MongoCollection $collection = null) {
		if (!$this->collection) {
			$this->collection = $collection;
		}
		try {
			$result = $collection->find($params, $fields);
			return iterator_to_array($result);
		} catch (Exception $e) {
			throw new Exception('No dataebase connection!');
		}
	}

	public function insert(array $values, MongoCollection $collection = null) {
		if (!$this->collection) {
			$this->collection = $collection;
		}
		try {
			$result = $collection->insert($values);
			return !$result ? false : true;
		} catch (Exception $e) {
			throw new Exception('No database connection!');
		}
	}

}