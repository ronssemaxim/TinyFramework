<?php
/*
* form.php: used to create & validate a form
*/
require_once 'includes/Extensions/Forms/formElement.php';
class Form {
	public $elements = array(); 		// list of elements within this form
	private $method,					// name of the required method (uppercase)
			$customErrors = array(),	// list of custom errors
			$customNotices = array();	// list of custom notices
	const NUMBER = 'number', 
		CHECKBOX = 'checkbox',
		PASSWORD = 'password',
		TEXTAREA = 'textarea',
		TEXT = 'text',
		SUBMIT = 'submit',
		BUTTON = 'button',
		RADIO = 'radio',
		SELECT = 'select',
		FILE = 'file';

	public function getSkipFunctions() {
		return array(
			'addElement',
			'addError',
			'addNotice',
			'getErrorStrings',
			'getNoticeStrings',
			'hasErrors',
			'hasNotices',
			'isValid',
			'validate',
			'getValues',
			'getElement',
			'getHTML'
		);
	}

	// create a form
	// $method (optional): required method. Atm only POST & GET
	public function createForm($framework, $method = 'POST') {
		$this->method = strtoupper($method);
		return $this;
	}



	// create and return a new element
	public function addElement($name, $type = 'text', $labelText, $value = null) {
		$element = new FormElement($name, $type, $labelText, $value);
		$element->form = $this;
		$this->elements[$name] = $element;
		return $element;
	}

	// add an error to the form
	// two possible ways to ad an error:
	//  a) nameless: addError('My error string');
	//  b) with name: addError('elementName', 'My error string');
	public function addError($param1, $param2 = null) {
		if($param2 == null) {
			$this->customErrors[] = $param1;
		}
		else {
			$this->elements[$param1]->errorString = $param2;
		}
	}

	// add an error
	// @see: addError
	// notice: only one error string is currently supported per element
	public function addNotice($param1, $param2 = null) {
		if($param2 == null) {
			$this->customNotices[] = $param1;
		}
		else {
			$this->element[$param1]->noticeString = $param2;
		}
	}

	// get all error strings in name-error format
	// 
	public function getErrorStrings() {
		$arr = array();
		foreach ($this->elements as $el) {
			if(!empty($el->errorString))
				$arr[$el->name] = $el->errorString;
		}
		foreach ($this->customErrors as $err) {
			$arr[] = $err;
		}
		return $arr;
	}

	// get all notice strings
	// @see: getErrorStrings
	public function getNoticeStrings() {
		$arr = array();
		foreach ($this->elements as $el) {
			if(!empty($el->noticeString))
				$arr[$el->name] = $el->noticeString;
		}
		foreach ($this->customNotices as $notice) {
			$arr[] = $notice;
		}
		return $arr;
	}

	// speaks for itself (true/false)
	public function hasErrors() {
		return count($this->getErrorStrings()) > 0 || count($this->customErrors) > 0;
	}

	// speaks for itself (true/false)
	public function hasNotices() {
		return count($this->customNotices) > 0;
	}

	// speaks for itself (true/false)
	public function isValid() {
		return $this->validate();
	}

	// validates the form
	public function validate() {
		$ret = true;
		if($_SERVER['REQUEST_METHOD'] != $this->method) return false;

		$vars = array();
		switch($this->method) {
			case 'POST':
				$vars = $_POST;
				break;
			case 'GET':
				$vars = $_GET;
				break;
		}

		foreach ($this->elements as $element) {
			if($element->validate($vars) !== true) $ret = false; // don't break here, because other errors & notices will be skipped
		}
		return $ret;
	}

	// get all the values from the form
	// $skipNulls (optional): whether or not a null value is returned within the array of elements
	// notice: the elements won't have any user posted value untill the @see: validate() function is called
	public function getValues($skipNulls = true) {
		$arr = array();
		foreach ($this->elements as $element) {
			if($element->value === null) {
				if($element->type == 'checkbox') $element->value = false; // checkboxes are always something special
				else if($skipNulls) continue;
			}
			$arr[$element->name] = htmlspecialchars($element->value, ENT_QUOTES, 'UTF-8'); // XSS
		}
		return $arr;
	}

	// get element by name
	public function getElement($name) {
		return $this->elements[$name];
	}

	// get html for this form
	// an alternative would be to manually call $element->getHTML, or $element->getHTMLLabel & $element->getHTMLElement
	public function getHTML() {
		$ret = '<form method="'.$this->method.'" action="#"><dl>';
		foreach ($this->elements as $element) {
			$ret .= $element->getHTML();
		}
		$ret .= '</dl></form>';
		return $ret;
	}
}
?>