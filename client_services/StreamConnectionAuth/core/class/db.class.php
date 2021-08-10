<?php defined('__GX__') or die('Access denied!');

class DB extends EObject implements DBInterface {

	CONST VERSION = '3.0.0';
	
	/** @var string Internal variable to hold the query sql */
	var $_sql = '';
	/** @var int Internal variable to hold the database error number */
	var $_errorNum = 0;
	/** @var string Internal variable to hold the database error message */
	var $_errorMsg = '';

	/** var Internal variable to hold the connector resource */
	/**
	 * @var mysqli
	 */
	var $_resource = '';

	/** @var Internal variable to hold the last query cursor */
	var $_cursor = null;
	/** @var boolean Debug option */
	var $_debug = 0;
	/** @var int The limit for the query */
	var $_limit = 0;
	/** @var int The for offset for the limit */
	var $_offset = 0;
	/** @var int A counter for the number of queries performed by the object instance */
	var $_ticker = 0;
	/** @var string database table prefix string */
	var $_table_prefix = '';
	/** @var array A log of queries */
	var $_log = null;
	/** @var string The null/zero date string */
	var $_nullDate = '0000-00-00 00:00:00';
	/** @var string Quote for named objects */
	var $_nameQuote = '`';

	/**
	 * Database object constructor
	 * @param string Database host
	 * @param string Database user name
	 * @param string Database user password
	 * @param string Database name
	 * @param string Common prefix for all tables
	 * @param boolean If true and there is an error, go offline
	 */
	/*function __construct($table_prefix = '', $dbEncoding = 'UTF8') {
		// perform a number of fatality checks, then die gracefully
		if (!$this -> _resource)
			$this -> _resource = new mysqli(Easy::DBHost, Easy::DBUser, Easy::DBPass, Easy::DBName);

		if (mysqli_connect_error()) {
			die('Connect Error (' . mysqli_connect_errno() . ') ' . mysqli_connect_error());
		}

		if (isset($this -> _resource) && $this -> _resource != '' && !$this -> _resource -> query('SET NAMES ' . $dbEncoding)) {
			throw new ErrorException($this -> _resource -> error, $this -> _resource -> errno);
		}

		$this -> _table_prefix = $table_prefix;
		$this -> _ticker = 0;
		$this -> _log = array();
	}*/
	function __construct($connect = array(), $table_prefix = '', $dbEncoding = 'UTF8') {
		if (!empty($connect) && isset($connect['DBHost']) && isset($connect['DBUser']) && isset($connect['DBPass']) && isset($connect['DBName'])){
			$this -> _resource = new mysqli($connect['DBHost'], $connect['DBUser'], $connect['DBPass'], $connect['DBName']);
		} else {
			// perform a number of fatality checks, then die gracefully
			if (!$this -> _resource)
				$this -> _resource = new mysqli(Easy::DBHost, Easy::DBUser, Easy::DBPass, Easy::DBName);
		}

		if (mysqli_connect_error()) {
			die('Connect Error (' . mysqli_connect_errno() . ') ' . mysqli_connect_error());
		}

		if (isset($this -> _resource) && $this -> _resource != '' && !$this -> _resource -> query('SET NAMES ' . $dbEncoding)) {
			throw new ErrorException($this -> _resource -> error, $this -> _resource -> errno);
		}

		$this -> _table_prefix = $table_prefix;
		$this -> _ticker = 0;
		$this -> _log = array();
	}

	/**
	 * @param int
	 */
	function debug($level) {
		$this -> _debug = intval($level);
	}

	/**
	 * @return int The error number for the most recent query
	 */
	function getErrorNum() {
		return $this -> _errorNum;
	}

	/**
	 * @return string The error message for the most recent query
	 */
	function getErrorMsg() {
		return str_replace(array("\n", "'"), array("\n", "\'"), $this -> _errorMsg);
	}

	/**
	 * Get a database escaped string
	 * @return string
	 * @deprecated  use escape() function
	 */
	function getEscaped($text) {
		return $this -> escape($text);
	}

	/**
	 * Get a database escaped string
	 * @return string
	 */
	function escape($text) {
		return mysqli_real_escape_string($this -> _resource, $text);
	}

	/**
	 * Get a quoted database escaped string
	 * @return string
	 */
	function quote($text) {
		return '\'' . $this -> getEscaped($text) . '\'';
	}

	/**
	 * Quote an identifier name (field, table, etc)
	 * @param string The name
	 * @return string The quoted name
	 */
	function nameQuote($s) {
		$q = $this -> _nameQuote;
		if (strlen($q) == 1) {
			return $q . $s . $q;
		} else {
			return $q{0} . $s . $q{1};
		}
	}

	/**
	 * @return string Quoted null/zero date string
	 */
	function getNullDate() {
		return $this -> _nullDate;
	}

	/**
	 * Sets the SQL query string for later execution.
	 *
	 * This function replaces a string identifier <var>$prefix</var> with the
	 * string held is the <var>_table_prefix</var> class variable.
	 *
	 * @param string The SQL query
	 * @param string The offset to start selection
	 * @param string The number of results to return
	 * @param string The common table prefix
	 */
	function setQuery($sql, $offset = 0, $limit = 0) {
		$this -> _sql = str_replace('#__', $this -> _table_prefix, $sql);
		$this -> _sql = $this -> _getLangSQL($this -> _sql);
		$this -> _limit = intval($limit);
		$this -> _offset = intval($offset);
	}

	/**
	 * @return string The current value of the internal SQL vairable
	 */
	function getQuery() {
		return "<pre>" . htmlspecialchars($this -> _sql) . "</pre>";
	}

	/**
	 * Execute the query
	 * @return mixed A database resource if successful, FALSE if not.
	 */
	function query() {
		if ($this -> _limit > 0 && $this -> _offset == 0) {
			$this -> _sql .= "\nLIMIT $this->_limit";
		} else if ($this -> _limit > 0 || $this -> _offset > 0) {
			$this -> _sql .= "\nLIMIT $this->_offset, $this->_limit";
		}
		if ($this -> _debug) {
			$this -> _ticker++;
			$this -> _log[] = $this -> _sql;
		}
		$this -> _errorNum = 0;
		$this -> _errorMsg = '';

		$this -> _cursor = $this -> _resource -> query($this -> _sql);
		if (!$this -> _cursor) {
			$this -> _errorNum = $this -> _resource -> errno;
			$this -> _errorMsg = $this -> _resource -> error . " SQL=$this->_sql";
			if ($this -> _debug) {
				trigger_error($this -> _resource -> error, E_USER_NOTICE);
				//echo "<pre>" . $this->_sql . "</pre>\n";
				if (function_exists('debug_backtrace')) {
					foreach (debug_backtrace () as $back) {
						if (@$back['file']) {
							echo '<br />' . $back['file'] . ':' . $back['line'];
						}
					}
				}
			}
			_debug($this -> getErrorMsg());
			return false;
		}
		return $this -> _cursor;
	}

	function close() {
		if ($this -> _resource) {
			$this -> _resource -> close();
		}
	}

	/**
	 * @return int The number of affected rows in the previous operation
	 */
	function getAffectedRows() {
		return $this -> _resource -> affected_rows;
	}

	function query_batch($abort_on_error = true, $p_transaction_safe = false) {
		$this -> _errorNum = 0;
		$this -> _errorMsg = '';
		if ($p_transaction_safe) {
			$si = $this -> _resource -> server_info;
			preg_match_all("/(\d+)\.(\d+)\.(\d+)/i", $si, $m);
			if ($m[1] >= 4) {
				$this -> _sql = 'START TRANSACTION;' . $this -> _sql . '; COMMIT;';
			} else if ($m[2] >= 23 && $m[3] >= 19) {
				$this -> _sql = 'BEGIN WORK;' . $this -> _sql . '; COMMIT;';
			} else if ($m[2] >= 23 && $m[3] >= 17) {
				$this -> _sql = 'BEGIN;' . $this -> _sql . '; COMMIT;';
			}
		}
		//_debugSQL( $this->_sql );
		$query_split = preg_split("/[;]+/", $this -> _sql);
		$error = 0;
		foreach ($query_split as $command_line) {
			$command_line = trim($command_line);
			if ($command_line != '') {
				$this -> _cursor = $this -> _resource -> query($command_line);
				if (!$this -> _cursor) {
					$error = 1;
					$this -> _errorNum .= $this -> _resource -> errno . ' ';
					$this -> _errorMsg .= $this -> _resource -> error . " SQL=$command_line <br />";
					if ($abort_on_error) {
						return $this -> _cursor;
					}
				}
			}
		}
		return $error ? false : true;
	}

	/**
	 * Diagnostic function
	 */
	function explain() {
		$temp = $this -> _sql;
		$this -> _sql = "EXPLAIN $this->_sql";
		$this -> query();

		if (!($cur = $this -> query())) {
			return null;
		}
		$first = true;

		$buf = "<table cellspacing=\"1\" cellpadding=\"2\" border=\"0\" bgcolor=\"#000000\" align=\"center\">";
		$buf .= $this -> getQuery();
		while ($row = mysqli_fetch_assoc($cur)) {
			if ($first) {
				$buf .= "<tr>";
				foreach ($row as $k => $v) {
					$buf .= "<th bgcolor=\"#ffffff\">$k</th>";
				}
				$buf .= "</tr>";
				$first = false;
			}
			$buf .= "<tr>";
			foreach ($row as $k => $v) {
				$buf .= "<td bgcolor=\"#ffffff\">$v</td>";
			}
			$buf .= "</tr>";
		}
		$buf .= "</table><br />&nbsp;";
		mysqli_free_result($cur);

		$this -> _sql = $temp;

		return "<div style=\"background-color:#FFFFCC\" align=\"left\">$buf</div>";
	}

	/**
	 * @return int The number of rows returned from the most recent query.
	 */
	function getNumRows($cur = null) {
		return mysqli_num_rows($cur ? $cur : $this -> _cursor);
	}

	/**
	 * This method loads the first field of the first row returned by the query.
	 *
	 * @return The value returned in the query or null if the query failed.
	 */
	function loadResult() {
		if (!($cur = $this -> query())) {
			return null;
		}
		$ret = null;
		if ($row = mysqli_fetch_row($cur)) {
			$ret = $row[0];
		}
		mysqli_free_result($cur);
		return $ret;
	}

	/**
	 * Load an array of single field results into an array
	 */
	function loadResultArray($numinarray = 0) {
		if (!($cur = $this -> query())) {
			return array();
		}
		$array = array();
		while ($row = mysqli_fetch_row($cur)) {
			$array[] = $row[$numinarray];
		}
		mysqli_free_result($cur);
		return $array;
	}

	/**
	 * Load a assoc list of database rows
	 * @param string The field name of a primary key
	 * @return array If <var>key</var> is empty as sequential list of returned records.
	 */
	function loadAssocList($key = '') {
		if (!($cur = $this -> query())) {
			return array();
		}
		$array = array();
		while ($row = mysqli_fetch_assoc($cur)) {
			if ($key) {
				$array[$row[$key]] = $row;
			} else {
				$array[] = $row;
			}
		}
		mysqli_free_result($cur);
		return $array;
	}

	function loadObject($class = 'stdClass') {
		$object = null;
		$cur = $this -> query();
		if ($cur) {
			$object = mysqli_fetch_object($cur, $class);
			if ($object) {
				mysqli_free_result($cur);
				return $object;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	/**
	 * Load a list of database objects
	 * @param string The field name of a primary key
	 * @return array If <var>key</var> is empty as sequential list of returned records.
	 * If <var>key</var> is not empty then the returned array is indexed by the value
	 * the database key.  Returns <var>null</var> if the query fails.
	 */
	function loadObjectList($key = '', $class = 'stdClass') {
		if (!($cur = $this -> query()))
			return array();
		$array = array();
		$obj = mysqli_fetch_object($cur, $class);
		while ($obj) {
			if ($key) {
				$array[$obj -> $key] = $obj;
			} else {
				$array[] = $obj;
			}
			$obj = mysqli_fetch_object($cur, $class);
		}
		mysqli_free_result($cur);
		return $array;
	}

	/**
	 * @return The first row of the query.
	 */
	function loadRow() {
		if (!($cur = $this -> query())) {
			return null;
		}
		$ret = null;
		if ($row = mysqli_fetch_row($cur)) {
			$ret = $row;
		}
		mysqli_free_result($cur);
		return $ret;
	}

	/**
	 * Load a list of database rows (numeric column indexing)
	 * @param int Value of the primary key
	 * @return array If <var>key</var> is empty as sequential list of returned records.
	 * If <var>key</var> is not empty then the returned array is indexed by the value
	 * the database key.  Returns <var>null</var> if the query fails.
	 */
	function loadRowList($key = null) {
		if (!($cur = $this -> query())) {
			return array();
		}
		$array = array();
		while ($row = mysqli_fetch_row($cur)) {
			if (!is_null($key)) {
				$array[$row[$key]] = $row;
			} else {
				$array[] = $row;
			}
		}
		mysqli_free_result($cur);
		return $array;
	}

	/**
	 * Document::db_insertObject()
	 *
	 * { Description }
	 *
	 * @param string $table This is expected to be a valid (and safe!) table name
	 * @param [type] $keyName
	 * @param [type] $verbose
	 */
	function insertObject($table, &$object, $keyName = NULL, $verbose = false) {
		$fmtsql = "INSERT INTO `$table` ( %s ) VALUES ( %s ) ";
		$fields = array();
		foreach (get_object_vars ( $object ) as $k => $v) {
			if (is_array($v) or is_object($v) or $v === NULL) {
				continue;
			}
			if ($k[0] == '_') {// internal field
				continue;
			}
			$fields[] = $this -> NameQuote($k);
			$values[] = $this -> Quote($v);
		}
		$this -> setQuery(sprintf($fmtsql, implode(",", $fields), implode(",", $values)));
		($verbose) && print "$sql<br />\n";
		if (!$this -> query()) {
			return false;
		}
		$id = mysqli_insert_id($this -> _resource);
		($verbose) && print "id=[$id]<br />\n";
		if ($keyName && $id) {
			$object -> $keyName = $id;
		}
		return true;
	}

	/**
	 * Document::db_updateObject()
	 *
	 * { Description }
	 *
	 * @param string $table This is expected to be a valid (and safe!) table name
	 * @param [type] $updateNulls
	 */
	function updateObject($table, &$object, $keyName, $updateNulls = true) {
		$fmtsql = "UPDATE $table SET %s WHERE %s";
		$tmp = array();
		foreach (get_object_vars ( $object ) as $k => $v) {
			if (is_array($v) or is_object($v) or $k[0] == '_') {// internal or NA field
				continue;
			}
			if ($k == $keyName) {// PK not to be updated
				$where = $keyName . '=' . $this -> Quote($v);
				continue;
			}
			if ($v === NULL && !$updateNulls) {
				continue;
			}
			if ($v == '') {
				$val = "''";
			} else {
				$val = $this -> Quote($v);
			}
			$tmp[] = $this -> NameQuote($k) . '=' . $val;
		}
		$this -> setQuery(sprintf($fmtsql, implode(",", $tmp), $where));
		return $this -> query();
	}

	/**
	 * @param boolean If TRUE, displays the last SQL statement sent to the database
	 * @return string A standised error message
	 */
	function stderr($showSQL = false) {
		return "DB function failed with error number $this->_errorNum" . "<br /><font color=\"red\">$this->_errorMsg</font>" . ($showSQL ? "<br />SQL = <pre>$this->_sql</pre>" : '');
	}

	function insertid() {
		return mysqli_insert_id($this -> _resource);
	}

	function getVersion() {
		return mysqli_get_server_info($this -> _resource);
	}

	function executeMultiQuery($query) {
		//_debugSQL( $query );
		return $this -> _resource -> multi_query($query);
	}

	/**
	 * @return array A list of all the tables in the database
	 */
	function getTableList() {
		$this -> setQuery('SHOW TABLES');
		return $this -> loadResultArray();
	}

	function tableExists($tableName) {
		$this -> setQuery('SHOW TABLES LIKE "' . $tableName . '"');
		$result = $this -> loadResult();
		if ($result && $result == $tableName) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * @param array A list of valid (and safe!) table names
	 * @return array A list the create SQL for the tables
	 */
	function getTableCreate($tables) {
		$result = array();

		foreach ($tables as $tblval) {
			$this -> setQuery('SHOW CREATE table ' . $this -> getEscaped($tblval));
			$rows = $this -> loadRowList();
			foreach ($rows as $row) {
				$result[$tblval] = $row[1];
			}
		}

		return $result;
	}

	/**
	 * @param array A list of valid (and safe!) table names
	 * @return array An array of fields by table
	 */
	function getTableFields($tables) {
		$result = array();

		foreach ($tables as $tblval) {
			$this -> setQuery('SHOW FIELDS FROM `' . $tblval . '`');
			$fields = $this -> loadObjectList();
			foreach ($fields as $field) {
				$result[$tblval][$field -> Field] = preg_replace("/[(0-9)]/", '', $field -> Type);
			}
		}

		return $result;
	}

	function getATableFields($table) {
		$result = array();

		$this -> setQuery('SHOW FIELDS FROM `' . $table . '`');
		$fields = $this -> loadObjectList();
		if ($fields && is_array($fields) && count($fields) > 0)
			foreach ($fields as $field)
				$result[] = $field -> Field;

		return $result;
	}

	/**
	 * Meghívja a szövegként átadott függvényt vagy eljárást olyan módon, ahogy át van adva, tehát a paramétereket is szövegként kell átadni
	 * @param string $procedureWithParams
	 * @return Ambigous <mixed, boolean>
	 */
	function callStoredProcedure($procedureWithParams) {
		$this -> setQuery('CALL ' . $procedureWithParams . ';');
		return $this -> query();
	}

	private function _getLangSQL($SQL) {

		if (defined('LANG') && LANG != MConfig::defLanguage && strpos($SQL, '_LANG_') !== false) {
			// SQL átírása, hogy nyelvesítve legyenek a kívánt mezők és táblák!
			$langData = $this -> _getLanguageTablesAndFields($SQL);

			if (count($langData['tables']) > 0 && count($langData['fields']) > 0) {

				// Nyelvesítendő mezők
				foreach ($langData['fields'] as $langField) {

					$field = str_replace('_LANG_FIELD', '', $langField);
					$fieldData = $this -> _getFieldData($field, $langData['tables']);

					$langTableName = $fieldData['table'] . '_' . strtolower(LANG);

					$SQL = str_replace($langField, 'COALESCE( `' . $langTableName . '`.' . $fieldData['name'] . ', `' . $fieldData['table'] . '`.' . $fieldData['name'] . ' ) as ' . $fieldData['alias'], $SQL);
				}

				// Nyelvesítendő táblák
				$langJoins = '';
				foreach ($langData['tables'] as $langTable) {

					$table = str_replace('_LANG_TABLE', '', $langTable);
					$tableData = $this -> _getTableData($table);

					if ($tableData['alias'] != $tableData['name']) {
						$langTableName = $tableData['name'] . '_' . strtolower(LANG) . ' as ' . $tableData['alias'] . '_' . strtolower(LANG);
					} else {
						$langTableName = $tableData['name'] . '_' . strtolower(LANG);
					}

					$langJoins .= 'LEFT JOIN ' . $langTableName . ' ON (' . $tableData['alias'] . '_' . strtolower(LANG) . '.id = ' . $tableData['alias'] . '.id) ' . "\n";
				}

				$SQL = str_replace('WHERE', $langJoins . 'WHERE', $SQL);
			}

		}

		return str_replace(array('_LANG_FIELD', '_LANG_TABLE'), array('', ''), $SQL);
	}

	private function _getLanguageTablesAndFields($SQL) {

		$arr_out = array();

		// Nyelvesítendő mezők nevei
		$p = preg_match_all('|SELECT(.*?)FROM|ism', $SQL, $arr_out);
		//mezok
		if (isset($arr_out[1][0])) {
			$langFields = explode(',', $arr_out[1][0]);
			array_walk($langFields, create_function('&$val', '$val = trim($val);'));
		} else {
			$langFields = array();
		}

		$_langFields = array();
		if (isset($langFields) && count($langFields) > 0)
			foreach ($langFields as $field)
				if (strpos($field, '_LANG_FIELD') !== false)
					$_langFields[] = $field;

		// Nyelvesítendő táblák nevei
		$p = preg_match_all('|JOIN(.*?)ON|ism', $SQL, $arr_out);
		// join tablak
		array_walk($arr_out[1], create_function('&$val', '$val = trim($val);'));
		$langTables = $arr_out[1];
		$p = preg_match_all('|FROM ([\w\.\` ]+)|is', $SQL, $arr_out);
		// from tabla
		$langTables = array_merge($arr_out[1], $langTables);

		$_langTables = array();
		if (isset($langTables) && count($langTables) > 0)
			foreach ($langTables as $table)
				if (strpos($table, '_LANG_TABLE') !== false)
					$_langTables[] = $table;

		// Kimenet összeállítása
		$result = array();
		$result['fields'] = $_langFields;
		$result['tables'] = $_langTables;
		return $result;
	}

	private function _getFieldData($field, $tables) {

		// variációk:
		//		mezo
		//		table.mezo
		//		mezo m
		//		table.mezo m
		//		mezo as m
		//		table.mezo as m

		// Table meghatározása
		if (strpos($field, '.') !== false) {
			$table = substr($field, 0, strpos($field, '.'));
		} else {
			$table = $tables[0];
		}

		// Alias meghatározása
		$name = '';
		if (strpos($field, ' as ') !== false) {
			$alias = substr($field, strpos($field, ' as ') + 4);
		} else if (strpos($field, ' ') !== false) {
			$alias = substr($field, strpos($field, ' ') + 1);
		} else if (strpos($field, '.') !== false) {
			$alias = substr($field, strpos($field, '.') + 1);
			$name = $alias;
		} else {
			$alias = $field;
			$name = $alias;
		}

		// Field meghatározása
		if ($name == '') {
			if (strpos($field, '.') !== false) {
				$start = strpos($field, '.');
				$name = substr($field, $start + 1, strpos($field, ' ') - $start);
			} else {
				$name = substr($field, 1, strpos($field, ' ') - 1);
			}
		}

		// Kimenet összeállítása
		$result = array();
		$result['name'] = $name;
		$result['table'] = $table;
		$result['alias'] = $alias;
		return $result;
	}

	private function _getTableData($table) {

		// variációk:
		//		table
		//		table t
		//		table as t

		// Alias meghatározása
		if (strpos($table, ' as ') !== false) {
			$alias = substr($table, strpos($table, ' as ') + 4);
			$name = substr($table, 0, strpos($table, ' as '));
		} else if (strpos($table, ' ') !== false) {
			$alias = substr($table, strpos($table, ' ') + 1);
			$name = substr($table, 0, strpos($table, ' '));
		} else {
			$alias = $table;
			$name = $alias;
		}

		// Kimenet összeállítása
		$result = array();
		$result['name'] = $name;
		$result['alias'] = $alias;
		return $result;
	}

}

function _debug($text) {
	if (!defined('_AJAX_') && Easy::debug != 0)
		print '<pre>' . print_r($text . '<br />', true) . '</pre>';
}