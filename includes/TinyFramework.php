<?php
/**
*	TinyFramework.php; made by Maxim Ronsse (ronsse.maxim@gmail.com) as a lightweight & fast php framework
*	
*/
include 'includes/DB.php';

class TinyFramework { // ArrayAccess to enable the user to do $framework['var']
	public  $routes,							// list of routes which are linked to the controllers (not controller specific routes)
			$baseUrl, $relativeUrl, 			// eg http//url/real/dir/controller/dir : baseUrl = /real/der relativeUrl =  /controller/dir
			$db = array(),						// contains the database controllers
			$allowEmptyVariables = true;
	private $controllerDir = './controllers/',	// duuh..
			$controllers = array(),				// list of controllers; all of them will be called, but the 'run' function is only called when the user's request requires further processing within the controller
			$defaultUrl,						// default controller to call (based on url)
			$hasAbsoluteUrls = false,			// another hasAbsoluteUrls variable here; improves processing speed
			$debug = false;

	private $customVars = array();

	// construct function which can handle up to two optional parameters:
	// 	$routes: array containing the url's as key and the controller name as value, eg:
	// 		[
	// 			'/forum'  => 'ForumController',
	// 			'/home'   => 'HomeController',
	// 			'/auth'   => 'AuthController',
	// 			'default' => '/home'
	// 		]
	//	$debug: true/false; shows or hides errors
	//  $autorun: automatically run the controller
	public function __construct($routes = array(), $debug = false, $rethrowException = true, $autorun = false) {
		session_start();
		$this->routes = $routes;
		$this->debug = $debug;

		// set handler for fatal errors
		//register_shutdown_function(array(&$this, "fatalHandler"));
		set_error_handler(array(&$this, "fatalHandler"));


		// set debugging options, just in case a fatal error occurs
		if($debug) {
			ini_set('error_reporting', -1);
			ini_set('display_errors', 1);
		}
		else {
			ini_set('error_reporting', 0);
			ini_set('display_errors', 0);
		}

		if($autorun)
			$this->Run();
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

	public function GetDB($name) {
		return $this->db[$name];
	}

	public function AddDB($name, $driver = null, $location = null, $dbName = null, $user = null, $pass = null, $port = null) {
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
				if($this->debug)
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

		$this->db[$name] = $newDb;
		return $newDb;
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


	/* EXCEPTION HANDLING */

	private function handleException($e) {
		if($e == null) {
			$file = 'Unknown';
			$line = 'Unknown';
			$msg = 'Fatal error';
		}
		else {
			$file = $e->getFile();
			$line = $e->getLine();
			$msg = $e->getMessage();
		}


		$logId = $e == null ? 'Unknown' : chr(mt_rand(97, 122)).substr(md5(time()), 1);
		// log to file
		$myFile = __DIR__ . "/../logs/error.txt";
		$fh = fopen($myFile, 'a');
		$stringData = date('d/m/Y H:i:s')."\t".(is_a($e, 'Error') ? 'Fatal ' : '')."Error thrown in ".$file." on line ".$line.": ".$msg.". Log id: $logId\n";
		fwrite($fh, $stringData);
		fclose($fh);
		if($e != null) {			
			// details
			$myFile = __DIR__ . "/../logs/errorDetails/$logId.txt";
			$fh = fopen($myFile, 'a');
			$stringData = "Date: ".date('d/m/Y H:i:s')."\n";
			$stringData .= (is_a($e, 'Error') ? 'Fatal ' : '')."Error thrown in ".$file." on line ".$line.": ".$msg."\n";
			$stringData .= "Log id: $logId\n\n";

ob_start();
var_dump($e->getTrace());
$result = ob_get_clean();
			$stringData .= "Trace: \n".$result;
			fwrite($fh, $stringData);
			fclose($fh);
		}

		// display log, rethrow, or show user error page
		if($this->debug) {
			$this->printDebugTrace($e);
		}
		else
		if($rethrowException == true) { // rethrow exception if desired
			throw $e;
		}
		else { // default error page
			?><!DOCTYPE html>
			<html>
			<head>
				<title>Error 500</title>
				<meta charset="utf8" />
				<style>
					body {
						background-color: #111;
						background-repeat: no-repeat, repeat;
						background-position: 0 0;
						background-size: 100% auto, 400px 400px;
						padding: 20px;
						font-family: "URW Palladio L",Palatino,"Book Antiqua","Palatino Linotype",serif;
						color: rgb(232,220,188);
						font-size: 13px;
						letter-spacing: 1px;
						max-width: 1024px;
						margin: 0 auto;
					}
				</style>
			</head>

			<body>
				<h1>Error 500</h1>
				An error occured while trying to process the page you requested!
			</body>

			</html>
			<?php
		}
	}

	// Print a user friendly error page, using the given $e exception
	private function printDebugTrace($e) {
		?><!DOCTYPE html>
		<html>
		<head>
			<title>Error debugging</title>
			<meta charset="utf8" />
			<style>
				body {
					font-family: Arial;
				}
				a {
					background: #0AD;
					color: white;
					display: inline-block;
					text-decoration: none;
					border-radius: 4px;
					padding: 5px 15px;
					float: right;
					transition: all .4s;
					-webkit-transition: all .4s;
				}
				a:hover {
					background: #08C;
				}
				a:active {
					box-shadow: 0 0 10px rgba(0, 0, 0, .4) inset;
				}
				.args {
					display: none;
				}
				.args div {
					padding-left: 20px;
					border-left: 1px dotted #CCC;
					display: none;
				}

				.args p {
					border-radius: 4px;
					background: #FFF;
					transition: all .2s;
					-webkit-transition: all .2s;
				}
				.args p.red {
					background: #FF5878
				}
				.args p.green {
					background: #58FF78
				}
				.args p.clickable {
					cursor: pointer;
					text-decoration: underline;
				}

				div.trace {
					margin-left: 20px;
					border-bottom: 1px solid #CCC;
				}

				div.code span.active {
					background: #C6C6C6;
					display: block;
					border-radius: 4px;
					color: #000;
				}
				div.code span.active.red {
					background: #FF5878;
				}

				span.fatal {
					background: #F00;
					color: white;
					text-shadow: 0 0 4px white;
					border-radius: 4px;
					padding: 3px 8px;
					animation: redBlink .8s linear infinite;
				}

				@keyframes redBlink {
					0% {
						background-color: red;
					}
					49% {
						background-color: red;
					}
					50% {
						background-color: white;
					}
					100% {
						background-color: white;
					}
				}
			</style>
		</head>
		<body>
			<h1>Error debugging</h1>
			<?php
			if($e == null) {
				echo 'Fatal unkown error, no details available :/';
			}
			else {
				echo (is_a($e, 'Error') ? '<span class="fatal">Fatal</span> ' : '').'Error thrown in '.$e->getFile().' on line '.$e->getLine().': '.$e->getMessage();


				// if it's a custom error, remove first trace item, because it is the location where the error was instantiated
				$trace = $e->getTrace();
				if(is_a($e, 'Error')) {		
					array_shift($trace);
				}
				foreach ($trace as $key => $value) {
					echo '<h2>'.(isset($value['class']) ? $value['class'] : $value['file'] ).($key == 0 ? ' (origin)' : '').'</h2><div class="trace">';
					if(isset($value['line']))
						echo '<p>Called on line '.$value['line'].'</p>';
					else
					if($key == 0) {
						echo '<p>Called on line '.$e->getLine().'</p>';
						$value['file'] = $e->getFile();
						$value['line'] = $e->getLine();
					}

					if(isset($value['file']) || $key == 0) {

						$contents = file_get_contents($value['file']);
						$lines = explode("\n", $contents);
						echo '<div class="code">';
						for($i = max(0, $value['line']-6); $i < $value['line']+5 && $i < count($lines); $i++) {
							if($i == $value['line']-1)
								echo '<span class="active'.($key == 0 ? ' red' : '').'">';
							echo str_replace("\t", "&nbsp;&nbsp;&nbsp;&nbsp;", $lines[$i]).'<br />';
							if($i == $value['line']-1)
								echo '</span>';
						}
						echo '</div>';
					}

					if(isset($value['function'])) {
						echo '<a title="Click to view arguments" href="#">Arguments ('.count($value['args']).')</a>';
						echo '<p>In function '.$value['function'].'</p>';
						echo '<div class="args">';
						var_dump($value['args']);
						echo '</div>';
					}
					echo '</div>';
				}
				?>
				<script src="//code.jquery.com/jquery-1.11.3.min.js"></script>
				<script type="text/javascript">
					$(document).ready(function() {
						$('a').click(function(e) {
							e.preventDefault();
							var args = $(' .args', $(this).parent());

							var lines = $(args).html().split(/\n/);
							var spacesLeft = 0;

							var txt = "";

							var isClickable = function(line) {
								return (line.indexOf('array(') === 0 ||  line.indexOf('object(') === 0 ? ' class="clickable" title="Click to expand"' : '');
							}
							for(var i = 0; i < lines.length; i++) {
								j = 0;
								while(lines[i][j] == ' ')
									j++;


								lines[i] = lines[i].trim();
								if(j > spacesLeft) {
									lines[i] = '<div><p' + isClickable(lines[i]) + '>' + lines[i] + '</p>';
								}
								else
								if(j < spacesLeft) {
									lines[i] = '</div><p' + isClickable(lines[i]) + '>' + lines[i] + '</p>';
								}
								else {
									lines[i] = '<p' + isClickable(lines[i]) + '>' + lines[i] + '</p>';
								}

								spacesLeft = j;
							}
							$(args).html(lines.join(''));

							args.slideToggle();
						});
						$(document).on('click', 'div.args p', function() {
							var t = this;
							var div = $(this).next('div');
							if(div.length < 1) {
								$(t).addClass('red');
								setTimeout(function() {
									$(t).removeClass('red');
								}, 200);
							}
							else {
								$(t).addClass('green');
								setTimeout(function() {
									$(t).removeClass('green');
								}, 200);
								div.slideToggle();
							}
						})
					});
				</script>
			</body>
			</html>
			<?php
			}
		exit(0);
	}

	public function fatalHandler($errno, $errstr, $errfile, $errline) {
		$e = new Error($errstr, $errno, $errfile, $errline = $errline);
		TinyFramework::handleException($e);
		return true;
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
}


// custom error class to initialize an Exception with custom attributes 
class Error extends Exception{
	public function __construct($message, $code, $file, $line) {
		$this->message = $message;
		$this->code = $code;
		$this->file = $file;
		$this->line = $line;
	}

	public function setTrace($trace) {
		$this->trace = $trace;
	}
}