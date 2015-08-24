<?php
/**
*	Framework.php, extension for TinyFramework; made by Maxim Ronsse (ronsse.maxim@gmail.com) as an extension to access a database
*	
*/
include 'includes/Extensions/DB/DB.php';

class TFExtensionDB { // ArrayAccess to enable the user to do $framework['var']
	public function initialize($framework) {
		$framework->db = array();
	}

	/* get db by name */
	public function GetDB($framework, $name) {
		return $framework->db[$name];
	}

	/**
	* Add a database
	*	$name: unique name to access it later, or an array containing all the paramaters as an associative array
	*	$driver: driver name (mysql, mssql, ...) (optional)
	*	$location: location of the database (file/host) (optional)
	*	$dbName: name of the database (optional)
	*	$user: username (optional)
	*	$pass: password (optional)
	*	$port: port to access the database (optional)
	*
	*/
	public function AddDB($framework, $name, $driver = null, $location = null, $dbName = null, $user = null, $pass = null, $port = null) {
		$dsn = null;
		$options = array(
			PDO::ATTR_PERSISTENT => true
		);
		$newDb = null;

		$skipCredentials = false;
		if(is_array($name)) {
			if(isset($name['port'])) $port = $name['port'];

			if(isset($name['pass'])) $pass = $name['pass'];
			else if(isset($name['password'])) $pass = $name['password'];

			if(isset($name['user'])) $user = $name['user'];
			else if(isset($name['username'])) $user = $name['username'];

			if(isset($name['dbName'])) $dbName = $name['dbName'];
			else if(isset($name['db'])) $dbName = $name['db'];

			if(isset($name['location'])) $location = $name['location'];
			else if(isset($name['host'])) $location = $name['host'];

			if(isset($name['driver'])) $driver = $name['driver'];
			else if(isset($name['type'])) $driver = $name['type'];

			if(isset($name['name'])) $name = $name['name'];
			else if(isset($name['identifier'])) $name = $name['identifier'];
		}
		if($dbName == null) throw new Exception("Please set the database name using the db/dbName parameter", 1);
		if($location == null) throw new Exception("Please set the DB location using the location/host parameter", 1);			
		if($driver == null) throw new Exception("Please set the DB driver using the driver/type parameter", 1);			
		if($name == null) throw new Exception("Please set the DB name using the name/identifier parameter", 1);			

		switch($driver) {
			case 'dblib':
			case 'mssql':
			case 'sybase':
			case 'mysql':
				if($port == null) {
					$dsn = "$driver:host=$location;dbname=$dbName";
				}
				else {
					if($driver == 'mysql')
						$dsn = "$driver:host=$location;port=$port;dbname=$dbName";
					else
						$dsn = "$driver:host=$location:$port;dbname=$dbName";
				}

				
				if($driver == 'mysql')
					$options[PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES utf8';
				break;
			case 'sqlite':
			case 'sqlite2':
				// sqlite doesn't require user/pass/host/port
				$newDb = new DB("$driver:".($location == 'memory' ? ':memory:' : $location));
				break;
			case 'pgsql':
				$skipCredentials = true;
				$dsn = "pgsql:host=$location;";
				if($port != null)
					$dsn .= "port=$port;";
				$dsn .= "dbname=$dbName;";
				if($user != null) {
					$dsn .= "user=$user;";
					if($pass != null)
						$dsn .= "password=$pass;";
				}
				// @TODO
				return;
				break;
			default:
				if($framework->debug)
					throw new Exception("Unsupported database driver", 1);
				return null;
		}

		if($newDb == null) {
			if($user == null || $skipCredentials)
				$newDb = new DB($dsn);
			else
			if($pass == null)
				$newDb = new DB($dsn, $user);
			else 
				$newDb = new DB($dsn, $user, $pass);
		}

		$newDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		$framework->db[$name] = $newDb;
		return $newDb;
	}
}