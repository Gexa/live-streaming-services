<?php defined( '__GX__' ) or die( 'ACCESS DENIED!' );

/**
 * File handler helper class
 */
class File {

	const VERSION = '3.0.0';
    /**
     * Function to make filenames safe
     * @param string $filename
     * @return string New filename
     */
    public static function makeSafe($filename) {
    	if (!$filename)
    		return false;

    	$info = pathinfo( $filename );
    	$new_filename = str_replace( array( isset($info['extension']) ? $info['extension'] : '', '.' ), '', $filename );
    	
    	$invalid = array(
    			'Š'=>'S', 'š'=>'s', 'Ð'=>'Dj','Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A',
    			'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E', 'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I',
    			'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U', 'Ú'=>'U',
    			'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss','à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a',
    			'å'=>'a', 'æ'=>'a', 'ç'=>'c', 'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i',
    			'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o', 'ù'=>'u',
    			'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y', 'ƒ'=>'f'
	    );
		
    	$new_filename = str_replace( '&', '-and-', $new_filename );
    	$new_filename = str_replace( ' ', '-', $new_filename );
    	$new_filename = str_replace( '--', '-', $new_filename );
    	$new_filename = str_replace( array_keys( $invalid ), array_values( $invalid ), $new_filename );
    	$new_filename = preg_replace( '/[^a-zA-Z0-9-\']/', '_', $new_filename );
		
		return $new_filename;
    }
    
    /**
     * Function to get File's extension
     * @param string $filename
     * @return mixed|boolean
     */
    public static function getExtension($filename) {
    	$info = pathinfo($filename);
    	
    	if (!empty($info))
    		return $info['extension'];
    	
		return false;
    }
    
    public static function getClassFromFilename($filename) {
    	if (strpos( $filename, '_' ) !== false) {
    		return str_replace( ' ', '', ucwords( str_replace( '_', ' ', $filename ) ) );
    	} else {
    		return ucfirst( $filename );
    	}
    }
    
    /**
    * @param Array $params /keys: src - full path of sources in array or string, dest - only filename
    * @return Array of copied files /full path/  or false on fault
    */
    public static function XCopy($params) {
    	
    	// no Source and destination
		if (!is_array($params) or !isset($params['src']))
			return false;
			
		$path = 
			PATH_PUBLIC . DS . 'files' . DS . 'webshop' . DS .
			((isset($params['type']) && $params['type']!='') 
				? $params['type'] 
				: 'other') . 
				((isset($params['id']) && (int)$params['id']!=0) 
					? (DS.$params['id']) 
					: '');
		
		if (!is_dir($path)) {
			mkdir($path, 0755, true);
			chmod($path, 0755);
		}
		
		$result = Array();
		
		if (!is_array($params['src'])) {
			
			$dest = (isset($params['dest']) && $params['dest']!='') 
				? $params['dest'] 
				: (self::makeSafe( basename($params['src']) ).'.'.self::getExtension(basename($params['src'])));
				
			if (copy( $params['src'], $path.DS.$dest ))
				$result[0] = $path.DS.$dest;
				
		} elseif (is_array($params['src'])) {
			foreach ($params['src'] AS $file) {
				
				$dest = (isset($params['dest']) && $params['dest']!='') 
					? $params['dest'] 
					: (self::makeSafe( basename($file) ).'.'.self::getExtension(basename($file)));
				
				if (copy( $file, $path.DS.$dest ))
					array_push($result, $path.DS.$dest);
			}
		}
		
		return $result;
		
    }
    
    public static function downloadFile($sourceURL, $destination) {

    	try {
	    	$targetFile = fopen( $destination, 'w' );
	    	
	    	$ch = curl_init( $sourceURL );
	    	
	    	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	    	curl_setopt( $ch, CURLOPT_FILE, $targetFile );
	    	curl_exec( $ch );
	    	
	    	fclose( $targetFile );
	    	
	    	if (file_exists( $destination ))
	    		return true;
	    	
    	} catch (Exception $e) {
    		return $e->getMessage();
    	}

    }

	/**
    * returns an upload directory structure eg. /ab/cde/ if filename like abcdefghijklmno.ext
    * 
    * @param string $filename
    * @return mixed $path on success bool false on fail 
    */
    public static function getUploadDir($filename, $base_path=null) {
		mb_internal_encoding('UTF-8');
		if (mb_strlen(trim($filename)) == 0)
			return false;
		if(!$base_path) {
			$base_path = PATH_BASE;	
		}
		
		$path_def = $base_path . DS . 'public_storage' . DS . 'files' . DS . 'uploads';
			
		$p1p = substr($filename, 0, 2);
		$p2p = substr($filename, 2, 3);
		
		$path_full = $path_def . DS . $p1p . DS . $p2p;
		if (!is_dir($path_full))	
			mkdir($path_full, 0755, true);
		
		return $path_full;
		
    }
    
    /**
    * get upload URL by uploadDir
    * 
    * @param string $filename
    * @return string URL
    */
    public static function getUploadUrl($filename) {
		
		$upload_dir = self::getUploadDir($filename);
		
		return str_replace(
			Array( PATH_BASE.DS , DS),
			Array( URL_BASE, '/'),
			$upload_dir
		);
		
    }
    
}
