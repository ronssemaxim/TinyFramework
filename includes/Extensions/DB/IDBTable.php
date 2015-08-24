<?php

// IDBTable interface, which forces the user to pass the tablename
interface IDBTable {
	public function __construct($tableName);
}

// a base table class, used to implement the basic sql commands
class BaseTable {
	public 
		$db = null,
		$tableName = null;

	public function Get($arr) {
		$sql = "SELECT * FROM ".$this->tableName." WHERE ".$this->getConditions($arr);
		$this->db->beginTransaction(); // transaction = faster
		$stmt = $this->db->prepare($sql);
		$stmt->execute(array_values($arr));
		$result = $stmt->fetchAll();
		$this->db->commit();
		return $result;
	}

	public function GetAll() {
		$this->db->beginTransaction(); // transaction = faster

		$sql = "SELECT * FROM ".$this->tableName;

		$stmt = $this->db->prepare($sql);
		$stmt->execute();
		$result = $stmt->fetchAll();
		$this->db->commit();
		return $result;
	}

	// insert one or multiple rows:
	//	a)
	//   array(
	//    'field' => 'value'
	//    ...
	//   )
	//  b)
	//   array(
	//    array(
	//     'field' => 'value'
	//     ...
	//    )
	//    ...
	//   )
	public function Insert($arr) {
		list($datafields, $question_marks, $insert_values) = $this->getQueryData($arr);
		$sql = "INSERT INTO ".$this->tableName." (" . implode(",", $datafields ) . ") VALUES " . implode(',', $question_marks);
		$this->db->beginTransaction(); // transaction = faster
		$stmt = $this->db->prepare($sql);
		$stmt->execute($insert_values);
		$this->db->commit();
	}

	public function Delete($arr) {
		$sql = "DELETE FROM ".$this->tableName." WHERE ".$this->getConditions($arr);

		$this->db->beginTransaction(); // transaction = faster
		$stmt = $this->db->prepare($sql);
		$stmt->execute(array_values($arr));
		$this->db->commit();
	}

	public function Update($arr, $vals) {
		//list($datafields, $question_marks, $insert_values) = $this->getQueryData($arr);
		$sql = "UPDATE ".$this->tableName." SET ";
		foreach ($vals as $key => $value) {
			$sql .= "$key = ?, ";
		}
		$sql = substr($sql, 0, -2);
		$sql .= " WHERE ".$this->getConditions($arr);

		$this->db->beginTransaction(); // transaction = faster
		$stmt = $this->db->prepare($sql);
		$stmt->execute(array_merge(array_values($vals), array_values($arr)));
		$this->db->commit();
	}

	public function Quote($string, $type) {
		return $this->db->quote($string, $type);
	}


	// generate the sql conditions (X = ? AND Y = ?)
	private function getConditions($arr) {
		$txt = "";
		foreach ($arr as $key => $value) {
			$txt .= "$key = ? AND ";
		}
		return substr($txt, 0, -5);
	}

	// converts array to datafields, question marks, and insert values
	private function getQueryData($arr, $single = false){
		$datafields = array();
		$question_marks = array();
		$insert_values = array();


		$tmp = array_slice($arr, 0, 1);
		$first = array_shift($tmp);
		if(is_array($first) && !$single) {
			foreach($arr as $name => $d){
				$question_marks[] = '('  . BaseTable::placeholders('?', sizeof($d)) . ')';
				$insert_values = array_merge($insert_values, array_values($d));
			}

			$tmp = array_slice($arr, 0, 1);
			$first = array_shift($tmp);
			foreach ($first as $key => $value) {
				$datafields[] = $key;
			}
		}
		else {
			$question_marks[] = '('  . BaseTable::placeholders('?', sizeof($arr)) . ')';
			$insert_values = array_merge($insert_values, array_values($arr));

			foreach ($arr as $key => $value) {
				$datafields[] = $key;
			}
		}

		return array($datafields, $question_marks, $insert_values);
	}

	private static function placeholders($text, $count = 0, $separator = ",") {
		$result = array();
		if($count > 0) {
			for($x = 0; $x < $count; $x++) {
				$result[] = $text;
			}
		}

		return implode($separator, $result);
	}
}
