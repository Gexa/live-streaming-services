<?php defined('__GX__') or die('Access denied!');

abstract class EObject {

	CONST VERSION = '3.0.0';

	protected static $aoInstance = array ();

	//protected function __construct() {}

	final public static function & getInstance() {
		$_args = func_get_args();
		$calledClassName = get_called_class ();
		if (! isset ( self::$aoInstance [$calledClassName] )) {
			$reflectionClass = new ReflectionClass($calledClassName);
			self::$aoInstance [$calledClassName] = $reflectionClass->newInstanceArgs($_args);
		}
		return self::$aoInstance [$calledClassName];
	}


	/**
	 * Memory leak védelem
	 * PHP 5.3 óta az unset meghívja a destructor-t és ez
	 * felszabadítja a belső objektumokat, hogy ne alakuljon ki memory leak!
	 */
	function __destruct() {
		foreach ($this as $key => $value) {
			if (is_object( $this->$key ) && !is_array( $this->$key )) {
				unset( $this->$key );
			}
		}
	}

	/**
	 * Returns a property of the object or the default value if the property is not set.
	 *
	 * @access	public
	 * @param	string $property The name of the property
	 * @param	mixed  $default The default value
	 * @return	mixed The value of the property
	 * @see		getProperties()
	 * @since	1.5
	 */
	function get($property, $default = null) {
		if (isset ( $this->$property )) {
			return $this->$property;
		}
		return $default;
	}

	/**
	 * Returns an associative array of object properties
	 *
	 * @access	public
	 * @param	boolean $public If true, returns only the public properties
	 * @return	array
	 * @see		get()
	 * @since	1.5
	 */
	function getProperties($public = true) {
		$vars = get_object_vars ( $this );

		if ($public) {
			foreach ( $vars as $key => $value ) {
				if ('_' == substr ( $key, 0, 1 )) {
					unset ( $vars [$key] );
				}
			}
		}

		return $vars;
	}

	/**
	 * Modifies a property of the object, creating it if it does not already exist.
	 *
	 * @access	public
	 * @param	string $property The name of the property
	 * @param	mixed  $value The value of the property to set
	 * @return	mixed Previous value of the property
	 * @see		setProperties()
	 * @since	1.5
	 */
	function set($property, $value = null) {
		$previous = isset ( $this->$property ) ? $this->$property : null;
		$this->$property = $value;
		return $previous;
	}

	/**
	 * Set the object properties based on a named array/hash
	 *
	 * @access	protected
	 * @param	$array  mixed Either and associative array or another object
	 * @return	boolean
	 * @see		set()
	 * @since	1.5
	 */
	function setProperties($properties) {
		$properties = ( array ) $properties; //cast to an array

		if (is_array ( $properties )) {
			foreach ( $properties as $k => $v ) {
				$this->$k = $v;
			}

			return true;
		}

		return false;
	}


	public function __get($propertyName) {
		$ppName = strtolower($propertyName);
		if (isset($this->$ppName))
			return $this->$ppName;
		return null;
	}

	public function __set($propertyName, $value) {
		$ppName = strtolower($propertyName);
		if (isset($this->$ppName))
			$this->$ppName = $value;
	}

	/**
	 * Object-to-string conversion.
	 * Each class can override it as necessary.
	 *
	 * @access	public
	 * @return	string This name of this class
	 * @since	1.5
	 */
	function toString() {
		return get_class ( $this );
	}

}

/*
 * Mivel ez a fuggveny csak az 5.3-as PHP ben van jelen..
 */
if (! function_exists ( 'get_called_class' )) {
	function get_called_class($bt = false, $l = 1) {
		if (! $bt)
			$bt = debug_backtrace ();
		if (! isset ( $bt [$l] ))
			throw new Exception ( "Cannot find called class -> stack level too deep." );
		if (! isset ( $bt [$l] ['type'] )) {
			throw new Exception ( 'type not set' );
		} else
			switch ($bt [$l] ['type']) {
				case '::' :
					$lines = file ( $bt [$l] ['file'] );
					$i = 0;
					$callerLine = '';
					do {
						$i ++;
						$callerLine = $lines [$bt [$l] ['line'] - $i] . $callerLine;
					} while ( stripos ( $callerLine, $bt [$l] ['function'] ) === false );
					preg_match ( '/([a-zA-Z0-9\_]+)::' . $bt [$l] ['function'] . '/', $callerLine, $matches );
					if (! isset ( $matches [1] )) {
						// must be an edge case.
						throw new Exception ( "Could not find caller class: originating method call is obscured." );
					}
					switch ($matches [1]) {
						case 'self' :
						case 'parent' :
							return get_called_class ( $bt, $l + 1 );
						default :
							return $matches [1];
					}
				// won't get here.
				case '->' :
					switch ($bt [$l] ['function']) {
						case '__get' :
							// edge case -> get class of calling object
							if (! is_object ( $bt [$l] ['object'] ))
								throw new Exception ( "Edge case fail. __get called on non object." );
							return get_class ( $bt [$l] ['object'] );
						default :
							return $bt [$l] ['class'];
					}

				default :
					throw new Exception ( "Unknown backtrace method type" );
			}
	}
}