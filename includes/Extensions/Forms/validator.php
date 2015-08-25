<?php
/*
Validator.php is part of the TinyFramework. It's used to check if a given URL is valid for the given query
*/
class Validator {
	public static function MatchURL($url, $query) {
		array_shift($query); // remove first empty element
		$userUrl = explode('/', substr($url, 1)); // split url
		if(count($query) != count($userUrl)) return false; // check length; should be equal

		$vars = array(); // array containing the variables from the query
		foreach ($query as $i => $value) { // loop through
			if(strlen($userUrl[$i]) > 2 && $userUrl[$i][0] == '{' && substr($userUrl[$i], -1) == '}') { // check for variables
				if(strpos($userUrl[$i], ':') !== FALSE) { // find regex
					// get regex string
					$colPos = strpos($userUrl[$i], ':');
					$regex = substr($userUrl[$i], $colPos+1, -1);
					if(!@preg_match('/'.$regex.'/', $query[$i]))
						return false; // match regex

					// set variable for return
					$vars[substr($userUrl[$i], 1, $colPos-1)] = $query[$i];
				}
				else {
					// no regex found, but still pass the values
					$vars[substr($userUrl[$i], 1, -1)] = $query[$i];
				}
			}
			else {
				if($userUrl[$i] != $query[$i]) return false;
			}
		}
		// all elements matched; return variables
		return $vars;
	}
}
?>