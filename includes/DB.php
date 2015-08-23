<?php
/**
* DB.php by Maxim Ronsse (ronsse.maxim@gmail.com)
* 
* connects to DB, and can add (dynamically) tables
* 
* 
* 
*/
require_once 'includes/IDBTable.php';

// "extends" keyword to prevent the user having to access inner variables, eg: $db->pdoObject->...
class DB extends PDO implements ArrayAccess {
	private $pdo;
	public $tableDir = 'includes/Data/',
			$tables = array();

	// Add table to this db
	// Notice: does NOT actually "create" the table, just marks this table as existing. 
	public function AddTable($tableName, $className = null) {
		if($className != null && file_exists($this->tableDir.$className.'.php')) {
			require_once $this->tableDir.$className.'.php';
			$tbl = new $className($tableName);
		}
		else {
			$tbl = new BaseTable();
			$tbl->tableName = $tableName;
		}
		$tbl->db = $this;
		$this->tables[$tableName] = $tbl;
		return $tbl;
	}

	public function AddTables($arr) {
		foreach ($arr as $tableName => $className) {
			$this->addTable($tableName, $className);
		}
	}

	// GetTable return the requested table, or adds & returns the requested table
	// $this->tables[] may also be used, but non-existing tables will not be created automatically
	public function GetTable($name) {
		if(isset($this->tables[$name]))
			return $this->tables[$name];
		else
			return $this->addTable($name);
	}



	/* array access */
	public function offsetExists ($offset) {
		return array_key_exists($offset, $this->tables);
	}
 
	public function offsetGet ($offset) {
		return $this->GetTable($offset);
	}
 
	public function offsetSet ($offset, $value) {
		$this->tables[$offset] = $value;
    }

	public function offsetUnset ($offset) {
		if(!$this->propertyExists($offset)) 
			unset($this->customVars[$offset]);
		// no 'else', because unsetting pre-defined properties might harm the functioning of the framework
	}
}