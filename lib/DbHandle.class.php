<?php namespace OpenFuego\lib;

class DbHandle extends \PDO {
	
	public function __construct() {
		
		$dsn = \OpenFuego\DB_DRIVER
		. ":host=" . \OpenFuego\DB_HOST
		. ';port=' . \OpenFuego\DB_PORT
		. ';dbname=' . \OpenFuego\DB_NAME
		. ';';

		try {
	        parent::__construct($dsn, \OpenFuego\DB_USER, \OpenFuego\DB_PASS, array(
				\PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8' COLLATE 'utf8_unicode_ci';",
				\PDO::ATTR_PERSISTENT => true
			));
			
			$this->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		}
		
		catch (\PDOException $e) {
			Logger::error($e);
		}
	}
	
	public function __destruct() {
		// close db connection
	}
}