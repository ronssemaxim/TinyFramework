<?php
/**
*	TinyFramework.php; made by Maxim Ronsse (ronsse.maxim@gmail.com) as a lightweight & fast php framework
*	
*/

class TinyFramework { // ArrayAccess to enable the user to do $framework['var']
	public  $routes,							// list of routes which are linked to the controllers (not controller specific routes)
			$baseUrl, $relativeUrl, 			// eg http//url/real/dir/controller/dir : baseUrl = /real/der relativeUrl =  /controller/dir
			$allowEmptyVariables = true;
	private $controllerDir = './controllers/',	// duuh..
			$extensionDir = 'includes/Extensions',
			$controllers = array(),				// list of controllers; all of them will be called, but the 'run' function is only called when the user's request requires further processing within the controller
			$defaultUrl,						// default controller to call (based on url)
			$hasAbsoluteUrls = false,			// another hasAbsoluteUrls variable here; improves processing speed
			$extensions = array(),				// array containing all the extension objects
			$extensionSkipFunctions = array();	// functions to skip when using extensions

	private $customVars = array();

	// construct function which can handle up to two optional parameters:
	// 	$routes: array containing the url's as key and the controller name as value, eg:
	// 		[
	// 			'/forum'  => 'ForumController',
	// 			'/home'   => 'HomeController',
	// 			'/auth'   => 'AuthController',
	// 			'default' => '/home'
	// 		]
	// $options: array containing name-value options
	// $autorun: automatically run the controller (or manually call Run())
	public function __construct($routes = array(), $options = array(), $autorun = false) {
		session_start();
		$this->routes = $routes;

		// load extensions
		$this->loadExtensions();

		foreach ($options as $key => $value) {
			if(isset($this->$key)) $this->$key = $value;
		}

		// run
		if($autorun)
			$this->Run();
	}

	private function loadExtensions() {
		$directories = glob($this->extensionDir.'/*' , GLOB_ONLYDIR);
		foreach ($directories as $dir) {
			if(file_exists($dir.'/Framework.php')) {
				$contents = file_get_contents($dir.'/Framework.php');
				$classes = $this->get_php_classes($contents);
				require_once $dir.'/Framework.php';

				if(count($classes) >= 1) {
					$name = $classes[0];
					$obj = new $name();
					if(method_exists($obj, 'initialize')) $obj->initialize($this);
					if(method_exists($obj, 'getSkipFunctions')) {
						$skip = $obj->getSkipFunctions();
						foreach ($skip as $func) {
							$this->extensionSkipFunctions[] = $func;
						}
					}
					$this->extensions[] = $obj;
				}
			}
		}
	}

	private function get_php_classes($php_code) {
		$classes = array();
		$tokens = token_get_all($php_code);
		$count = count($tokens);
		for($i = 2; $i < $count; $i++) {
			if($tokens[$i - 2][0] == T_CLASS && $tokens[$i - 1][0] == T_WHITESPACE && $tokens[$i][0] == T_STRING) {
				$class_name = $tokens[$i][1];
				$classes[] = $class_name;
			}
		}
		return $classes;
	}

	// run this framework!
	public function Run() {
		// big try catch to catch any possible exception
		try {
			if(isset($this->routes['default'])) { // set default action
				$this->defaultUrl = $this->routes['default'];
				unset($this->routes['default']);
			}
			$this->initialize();

			// write access to log
			$myFile = "logs/access.txt";
			$fh = fopen($myFile, 'a');
			$stringData = date('d/m/Y H:i:s')."\t".$_SERVER['REQUEST_URI'];

			if(!empty($_SERVER['HTTP_CLIENT_IP']))
				$ip = $_SERVER['HTTP_CLIENT_IP'];
			else
			if(!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
				$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
			else
				$ip = $_SERVER['REMOTE_ADDR'];
			$stringData .= "\t IP = ".$ip."\t Method = ".$_SERVER['REQUEST_METHOD']."\n";
			fwrite($fh, $stringData);
			fclose($fh);
			$this->getController();
		}
		catch(Exception $e) {

			$this->HandleException($e);
		}
	}

	// get a url by name, and replace the variables with actual values
	// $name: name of the url
	// $params: array containing the variable-values respectively, eg:
	//  [
	//		'threadId'  => 12
	//		'commentId' => 314
	//	]
	// 
	// @see: IController.php/GetUrl
	public function GetUrl($name, $params = array()) {
		// call GetUrl for each controller and search for the url name there
		foreach ($this->controllers as $path => $contr) {
			$url = $contr->GetUrl($name, $params);
			if($url != null) {
				return $this->baseUrl.ltrim($url, '/');
			} 
		}
		return $this->baseUrl; // return base url if no controller knows about the requested url
	}

	// initialize the variables and the controllers
	private function initialize() {
		if(!isset($_SERVER['DOCUMENT_ROOT']) || $_SERVER['DOCUMENT_ROOT'] === '') {
			$_SERVER['DOCUMENT_ROOT'] = substr($_SERVER['SCRIPT_FILENAME'], 0, -strlen($_SERVER['SCRIPT_NAME']));
			putenv('DOCUMENT_ROOT='.$_SERVER['DOCUMENT_ROOT']);
		}
		if(!isset($_SERVER['REQUEST_URI'])) {
			$_SERVER['REQUEST_URI'] = $_SERVER['HTTP_X_ORIGINAL_URL'];
		}
		
		
		$this->baseUrl = rtrim(dirname(substr($_SERVER['SCRIPT_FILENAME'], strlen($_SERVER['DOCUMENT_ROOT']))), '/').'/';
		// document root = base path for the webserver to search the requested url. eg /var/www
		// script file name = full path to file, according to webserver (not URL). eg /var/www/index.php
		// DO NOT TRY TO SIMPLIFY THIS PROCESS, it's the only compatible way I've found which works on apache, nginx & IIS 


		foreach ($this->routes as $key => $value) { // loop through url's and corresponding controller names
			include $this->controllerDir.$value.'.php';	// include the controller
			$this->controllers[$this->routes[$key]] = new $this->routes[$key]($this); // instantiate the controller
			$this->controllers[$this->routes[$key]]->relativeUrl = $key; // set this controller's relative url

			// create controllers for absolute url's
			if($this->controllers[$this->routes[$key]]->hasAbsoluteUrls) {
				$this->hasAbsoluteUrls = true;
				foreach ($this->controllers[$this->routes[$key]]->routes as $subKey => $subValue) { // loop though routes within the controller, search '//' for absolute urls
					if(substr($subValue['url'], 0, 2) == '//') {
						$this->routes[$subValue['url']] = $value; // create custom route
						$this->controllers[$subValue['url']] = new $this->routes[$key]($this); // instantiate controller
						$this->controllers[$subValue['url']]->relativeUrl = '/';

						$this->controllers[$this->routes[$key]]->routes[$subKey]['url'] = $subValue['url']; // edit controller's route
					}
				}
			}
		}
	}


	// determine which controller is responsible for the requested url & call the controller + echo returned content
	private function getController() {
		$path = $_SERVER['REQUEST_URI']; // = the browsers url
		$path = explode('?', $path)[0]; // remove ? string
		if(!$this->allowEmptyVariables)
			$path = preg_replace('/(\/+)/','/', $path); // remove double slashes, if needed


		$path = trim($path, '/'); // Trim slash(es)
		$path = substr($path, strlen($this->baseUrl)-1); // remove baseUrl

		// check for empty path, as it might conflict in combination with allowEmptyVariables
		if(empty($path)) $elements = array();
		else $elements = explode('/', $path); // Split path on slashes

		if(!$this->allowEmptyVariables)
			$elements = array_filter($elements); // remove empty elements, if needed
		array_unshift($elements, ''); // prepend '' in front, so '/' is appended to the front further down the processing

		if(count($elements) <= 1) {                       // No path elements means home
			if($this->defaultUrl == null) echo '';
		    else header('location: '.$this->baseUrl.trim($this->defaultUrl, '/'));
		}
		else /*switch(array_shift($elements)) */
		{
			$tmpPath = $elements;

			// implode the elements starting with the most specific url (=the longest)
			do {
				$check = implode('/', $tmpPath);
				if(array_key_exists($check, $this->routes)) { // find the controller for this path
					$passPath = array_slice($elements, count($tmpPath)); // path to pass to the controller
					array_unshift($passPath, ''); // prepend '' again
					$this->relativeUrl = $passPath; // set relative url, so the controller can use this variable
					echo $this->controllers[$this->routes[$check]]->Run($this);	// who run this controller?
					return; // break the loop after the running is complete
				}
				if($this->hasAbsoluteUrls && array_key_exists('/'.$check, $this->routes)) { // find absolute url's
					$this->relativeUrl = $elements;
					echo $this->controllers['/'.$check]->Run($this);
					return;
				}
				array_pop($tmpPath); // pop an element from the array and repeat the loop
			} while(count($tmpPath) > 0); // stop the loop if there are no more elements left to check

			// if this line is reached, this means that no controller was callled
			http_response_code(404);
			header('HTTP/1.1 404 Not Found');
		}
	}


	/* array access. @TODO: 'implements ArrayAccess', seems to go in a loop somewhere  */
	public function offsetExists ($offset) {
		if($this->propertyExists($offset, false))  // return true, even if property is private
			return true;
		else 
			return array_key_exists($offset, $this->customVars);
	}
 
	public function offsetGet ($offset) {
		if($this->propertyExists($offset)) 
			return ${$offset};
		else 
			return $this->customVars[$offset];
	}
 
	public function offsetSet ($offset, $value) {
		if($this->propertyExists($offset)) 
			${$offset} = $value;
		else 
			$this->customVars[$offset] = $value;
	}

	public function offsetUnset ($offset) {
		if(!$this->propertyExists($offset)) 
			unset($this->customVars[$offset]);
		// no 'else', because unsetting pre-defined properties might harm the functioning of the framework
	}

	// check if property exists
	private function propertyExists($name, $checkPrivate = true) {
		if(property_exists('TinyFramework', $name)) {
			if($checkPrivate) {    			
				// check if not private
				$reflector = new ReflectionClass('TinyFramework');

				$prop = $reflector->getProperty($name);
				return !$prop->isPrivate();
			}
			else {
				return true;
			}
		}
		return false;
	}


	// gets called each time an unknown function is called
	function __call($func, $params){
		foreach($this->extensions as $ext) {
			if(method_exists($ext, $func) && !in_array($func, $this->extensionSkipFunctions)) {
				array_unshift($params, $this);
				return call_user_func_array(array($ext, $func), $params);
			}
		}
		throw new Exception("Call to undeclared function, or unknown extension method name'".$func."'", 1);
	}
}