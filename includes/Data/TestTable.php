<?php
require_once 'includes/Extensions/DB/IDBTable.php';

// an example of a custom Table class, used to create custom sql code
class TestTable extends BaseTable implements IDBTable {
	public function __construct($tableName) {
		$this->tableName = $tableName;
	}

	public function GetTotalRecords() {
		$stmt = $this->db->prepare("SELECT COUNT(*) FROM ".$this->tableName);
		$stmt->execute();
		return $stmt->fetch()[0];
	}
}