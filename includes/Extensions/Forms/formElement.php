<?php
/*
* formElement.php: used to create & validate the form
*/
class FormElement {
	public  $name,					// name of this elements, will be visible in html
			$value,					// value of this elements as the user sees/saw it
			$labelText,				// text to be displayed for this element's label
			$type,					// type of elements (text/checkbox/textarea/...)
			$options = null,		// array of possible options between which the user has to choose
			$min = 0,				// min length/value for this element
			$max = null,			// idemdito, max
			$notEmpty = false,		// whether or not the value for this element can be empty
			$forceBool = false,		// force boolean values (used for checkboxes)
			$forceDecimal = false,	// force decimal vlaues
			$isValid = true,		// if this elements matches all the conditions
			$form = null,			// parent form
			$errorString = null,	// errorstring (up to one)
			$noticeString = null;	// idem, for notices

	// a bunch of language strings, custom languages are supported
	private $strings = array(
		'EN' => array(
			'empty' => 'Please fill in the field',
			'option' => 'Please select an option',
			'file' => 'Please select a file',
			'number' => 'Only numeric values are allowed',
			'boolean' => 'Only boolean values are allowed',
			'minValue' => 'The minimum value for this field is ',
			'maxValue' => 'The maximum value for this field is ',
			'minLength' => 'The minimum length for this field is ',
			'maxLength' => 'The maximum length for this field is ',
		)
	);
	private $lang = 'EN'; // language, can be customized aswel

	// speaks for itself
	public function __construct($name, $type, $labelText = null, $value = null) {
		if($labelText == null) $labelText = $name;
		$this->name = $name;
		$this->type = $type;
		$this->labelText = $labelText;
		$this->value = $value;
		switch ($type) {
			case 'number':
				$this->forceDecimal = true;
				$this->notEmpty = false;
				$this->isValid = false;
				break;
			case 'checkbox':
				$this->isValid = false;
				$this->forceBool = true;
				break;
			case 'password':
			case 'textarea':
			case 'text':
				$this->notEmpty = true;
				$this->isValid = false;
				break;
			case 'submit':
				$this->isValid = true;
				break;
			case 'button':
				$this->isValid = true;
				break;
			case 'radio':
			case 'select':
				$this->options = array();
				$this->isValid = false;
				break;
			case 'file':
				$this->notEmpty = true;
				$this->isValid = false;
				break;
		}
	}

	// speaks for itself
	public function setValue($val) {
		$this->value = $val;
	}

	// if this element is required or not
	public function required($bool) {
		$this->notEmpty = $bool;
	}

	// validate this element & save the value
	public function validate($post) {
		$this->isValid = true;
		if(isset($post[$this->name])) {
			$this->value = $post[$this->name];
			if(!$this->notEmpty && $this->value == '') {

			}
			else switch($this->type) {
				case 'number':
					if($this->notEmpty === true && ($this->value == '' || $this->value != null)) {
						$this->errorString = $this->strings[$this->lang]['empty'];
						$this->isValid = false;
					}
					else {
						$this->checkMinNumberLength();
						$this->checkMaxNumberLength();
						$this->checkDecimal();
						$this->checkBool();
					}
					break;
				case 'checkbox':
					$this->value = $this->value !== null;
					$this->checkBool();
					break;
				case 'text':
				case 'password':
				case 'textarea':
					if($this->notEmpty && ($this->value == '' || $this->value == null)) {
						$this->errorString = $this->strings[$this->lang]['empty'];
						$this->isValid = false;
					}
					else {
						$this->checkMinStringLength();
						//echo '<br />check: '.($this->isValid === true ? 'yes' : 'no').'<br />';
						$this->checkMaxStringLength();
						$this->checkDecimal();
						$this->checkBool();
					}
					break;
				case 'radio':
				case 'select':
					if($this->notEmpty && ($this->value == '' || $this->value == null)) {
						$this->errorString = $this->strings[$this->lang]['option'];
						$this->isValid = false;
					}
					else {
						$this->checkOptions();
					}
					break;
				case 'file':
					break;
			}
		}
		else {
			if($this->type == 'checkbox') {
				$this->value = false;
			}
			else
			if($this->type == 'file') {
				if($this->notEmpty && !isset($_FILES[$this->name])) {
					$this->errorString = $this->strings[$this->lang]['file'];
					$this->isValid = false;
				}
			}
			else {
				$this->errorString = $this->strings[$this->lang]['empty'];
				$this->isValid = !$this->notEmpty;
			}
		}

		return $this->isValid;
	}

	/**
	*	checkOptions: checks if the value of this element is within the limits if it's possible options
	*
	*/
	public function checkOptions() {
		$ret = array_key_exists($this->value, $this->options);
		if(!$ret) {
			$ret = in_array($this->value, $this->options);
			if(!$ret) {
				$this->isValid = false;
				echo 'nein'.$this->name;
			}
		}
		return $ret;
	}

	// speaks for itself
	public function checkMinStringLength() {
		if(!$this->forceDecimal && $this->min != null) {
			$ret = strlen($this->value) >= $this->min;
			if(!$ret) {
				$this->isValid = false;
				$this->errorString = $this->strings[$this->lang]['minLength'].$this->min;
			}
			return $ret;
		}
		else {
			return true;
		}
	}

	// speaks for itself
	public function checkMaxStringLength() {
		if(!$this->forceDecimal && $this->max != null) {
			$ret = strlen($this->value) <= $this->max;
			if(!$ret) {
				$this->isValid = false;
				$this->errorString = $this->strings[$this->lang]['maxLenght'].$this->max;
			}
			return $ret;
		}
		else {
			return true;
		}
	}


	// speaks for itself
	public function checkMinNumberLength() {
		if($this->forceDecimal === true && $this->min != null) {
			$ret = $this->value < $this->min;
			if(!$ret) {
				$this->isValid = false;
				$this->errorString = $this->strings[$this->lang]['minValue'].$this->min;
			}
			return $ret;
		}
		else {
			return true;
		}
	}

	// speaks for itself
	public function checkMaxNumberLength() {
		if($this->forceDecimal === true && $ret = $this->max != null) {
			$ret = $this->value > $this->max;
			if($ret == false) {
				$this->isValid = false;
				$this->errorString = $this->strings[$this->lang]['maxValue'].$this->max;
			}
			return $ret;
		}
		else {
			return true;
		}
	}

	// speaks for itself
	public function checkDecimal() {
		if($this->forceDecimal == true) {
			$ret = preg_match('/\d/', $this->value);
			if($ret === false) {
				$this->isValid = false;
				$this->errorString = $this->strings[$this->lang]['numbers'];
			}
			return $ret;
		}
		else {
			return true;
		}
	}

	// speaks for itself
	public function checkBool() {
		if($this->forceBool == true) {
			if($this->value == 'true' || $this->value == '1' || $this->value == 1 || $this->value === true) { // sort of parsing
				$this->value = true;
				return true;
			}
			if($this->value == 'false' || $this->value == '0' || $this->value == 0 || $this->value === false) {
				$this->value = false;
				return true;
			}
			if(!$this->notEmpty && $this->value == '') {
				$this->value = false;
				return true;
			}
			$this->isValid = false;
			$this->errorString = $this->strings[$this->lang]['boolean'];
			return false;
		}
		else {
			return true;
		}
	}

	// get html for the label only
	public function getHTMLLabel() {
		return '<label for="'.$this->name.'">'.($this->type == 'submit' ? '&nbsp;' : $this->labelText).'</label>';
	}

	// get html for the label it's self only
	public function getHTMLElement() {
		switch($this->type) {
			case 'password':
			case 'text':
				return '<input type="'.$this->type.'" id="'.$this->name.'" name="'.$this->name.'" value="'.$this->value.'" '.($this->forceDecimal === false && ($this->min != null || $this->max != null) ? 'minlength="'.$this->min.'" maxlength="'.$this->max.'" pattern="'.($this->notEmpty ? '' : '.{0}|').'.{'.$this->min.','.$this->max.'}"' : ($this->forceDecimal === true && ($this->min != null || $this->max != null) ? 'pattern="\d{'.$this->min.','.$this->max.'}"' : '')).' '.($this->notEmpty ? 'required' : '').'/>';
			case 'textarea':
				return '<textarea name="'.$this->name.'" maxlength="'.$this->max.'">'.$this->value.'</textarea>';
			case 'number':
				return '<input type="number" name="'.$this->name.'" value="'.$this->value.'" min="'.$this->min.'" max="'.$this->max.'" '.($this->notEmpty ? 'required' : '').'/>';
			case 'submit':
				return '<input type="submit" id="'.$this->name.'" name="'.$this->name.'" value="'.$this->value.'" />';
			case 'select':
				$ret = '<select name="'.$this->name.'" id="'.$this->name.'">';
				foreach ($this->options as $key => $value) {
					$ret .= '<option value="'.$key.'"'.($this->value == $key ? ' selected' : '').'>'.$value.'</option>';
				}
				return $ret.'</select>';
			case 'radio':
				$ret = '';
				foreach ($this->options as $key => $value) {
					$ret .= '<input type="radio" name="'.$this->name.'" id="'.$this->name.'_'.$value.'" value="'.$value.'"'.($this->value == $value ? ' checked' : '').'/>';
					$ret .= '<label for="'.$this->name.'_'.$value.'">'.$value.'</label>';
				}
				break;
			case 'file':
				return '<input type="'.$this->type.'" id="'.$this->name.'" name="'.$this->name.'"'.($this->notEmpty ? 'required' : '').'/>';
			case 'checkbox':
				return '<input type="checkbox" name="'.$this->name.'" id="'.$this->name.'" '.($this->value !== null && $this->value === true ? 'checked' : '').'/>';
		}
	}

	// get html for the label & element
	public function getHTML() {
		$ret = '';
		if($this->type == 'checkbox') {
			$ret .= '<dt></dt><dd>'.$this->getHTMLElement().$this->getHTMLLabel().'</dd>';
		}
		else {
			$ret .= '<dt>'.$this->getHTMLLabel().'</label></dt><dd>';
			$ret .= $this->getHTMLElement();
			$ret .= '</dd>';
		}
		return $ret;
	}
}
?>