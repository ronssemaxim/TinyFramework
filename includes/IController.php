<?php
require_once 'includes/Twig/Autoloader.php';

interface IController {
	public function __construct($app);
}


class BaseController {
	public 
		$routes = array(),  		// array containing the routes for this controller only
		$relativeUrl, 				// relative url for this controller, doest NOT change according to the user's request
		$twig, 						// twig variable is located within the controller to improve loading time & memory usage
		$hasAbsoluteUrls = false;	// will be set to true if any absolute url is found (improve loading time)
	private $default, 				// default action to take when no route matches the request
			$onCheckFailure;		// what to do when a check fails

	// fill routes
	public function Routes($app, $arr) {
		$app->controller = $this;
		foreach ($arr as $key => $value) {
			if($key == 'default') {
				if(is_array($value))
					$this->default = $value;
				else
					$this->default = array(
						'function' => $value
					);
			} 
			else
			if($key == 'onCheckFailure') {
				$this->onCheckFailure = $value;
			}
			else {
				if(!$this->hasAbsoluteUrls && substr($key, 0, 2) == '//') $this->hasAbsoluteUrls = true;
				$value['url'] = $key;
				$this->routes[$value['name']] = $value;
			}
		}
	}

	// preloaded code before actual controller is called
	public function Preload($app) {
		// twig loader; only load twig if any controller was called
		Twig_Autoloader::register();
		$loader = new Twig_Loader_Filesystem('templates/');
		$this->twig = new Twig_Environment($loader, array(	
			'debug' => true,
			/*'cache' => 'includes/Twig/cache',*/
		));
		// inject global variables
		$this->twig->addGlobal('app', $app); 
		$this->twig->addGlobal('session', $_SESSION);

		// add access to static variables
		// {{ static('YourNameSpace\\ClassName', 'VARIABLE_NAME') }}
		$staticFunc = new \Twig_SimpleFunction('static', function ($class, $property) {
			if (property_exists($class, $property)) {
				return $class::$$property;
			}
			return null;
		});
		$this->twig->addFunction($staticFunc);

		// debug
		$this->twig->addExtension(new Twig_Extension_Debug());
	}

	// gets called by the router when the function should be within this controller
	public function Run($app) {
		// call function
		foreach ($this->routes as $key => $value) {
			$matched = Validator::MatchUrl('/'.trim($value['url'], '/'), $app->relativeUrl); // trim '/' for rel & abs urls
			if($matched !== false) {
				// match checkBefore statements
				$exec = true;
				if(array_key_exists('require', $value) && $exec) {
					if(array_key_exists('method', $value['require'])) {
						$exec = in_array($_SERVER['REQUEST_METHOD'], explode('|', strtoupper($value['require']['method'])));
					}
				}
				if(array_key_exists('checkBefore', $value) && $exec)
					$exec = call_user_func_array(array($this, $value['checkBefore']), array($app));

				if($exec === true) {
					$this->Preload($app);
					array_unshift($matched, $app); // append $app as the first parameter
					return call_user_func_array(array($this, $value['function']), $matched);
				}
				else
				if($this->onCheckFailure != null) {
					return $this->doOnCheckFailure($app);
				}
				else {
					return null;
				}
			}
		}
		if($this->default == null) {
			return null;
		}
		else {
			// default
			$exec = true;
			if(array_key_exists('checkBefore', $this->default))
				$exec = call_user_func_array(array($this, $this->default['checkBefore']), array($app));

			if($exec === true) {
				$this->Preload($app);
				return call_user_func_array(array($this, $this->default['function']), array($app));
			}
			else
			if($this->onCheckFailure != null) {
				return $this->doOnCheckFailure($app);
			}
			else {
				return null;
			}
		}
	}

	private function doOnCheckFailure($app) {
		if(array_key_exists('redirect', $this->onCheckFailure)) {
			header('Location: '.$app->getUrl($this->onCheckFailure['redirect']));
			exit(0);
		}
		else
		if(array_key_exists('echo', $this->onCheckFailure)) {
			return $this->onCheckFailure['echo'];
		}
	}

	public function GetUrl($name, $params = array()) {
		if(!isset($this->routes[$name])) return null; // find in routes
		$url = $this->routes[$name]['url'];
		//var_dump($this->routes[$name]);
		// replace variable names with values
		foreach ($params as $key => $value) {
			$url = BaseController::ReplaceQueryParameterByValue($url, $key, $value);
		}
		// replace any remaining variables by empty strings
		return ($this->hasAbsoluteUrls && substr($url, 0, 2) == '//' ? '' : $this->relativeUrl).preg_replace('/{.*}/', '', $url); // only append relativeUrl if not an absolute url
	}

	public static function ReplaceQueryParameterByValue($url, $name, $value) {
		return preg_replace('/{('.$name.')(:.*)?}/', $value, $url);
	}
}