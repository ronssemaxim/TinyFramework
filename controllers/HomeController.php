<?php
require_once 'includes/validator.php';
require_once 'includes/IController.php';

class HomeController extends BaseController implements IController {
	private $itemsPerPage = 50;

	function __construct($app) {
		$this->Routes($app, array(
			'/' => array(
				'function' => 'home',
				'name' => 'home'
			),
			'/test' => array(
				'function' => 'test',
				'name' => 'test'
			),
			'/parameter/{var:\d}' => array(
				'function' => 'test2',
				'name' => 'test2'
			),
			'/parameterWithoutRegex/{var}' => array(
				'function' => 'test3',
				'name' => 'test3'
			),
			'default' => 'home'
		));
	}

	public function test($app) {
		echo $app->GetDb('Test')->GetTable('mysqlTable')->GetTotalRecords();
		/* alternatives:
			$app->GetDb('Test')['mysqlTable']
			$app->db['Test']->GetTable('mysqlTable')
			$app['db']['Test']['mysqlTable']
			$app['db']['Test']->GetTable('mysqlTable')
			...
		*/
		return ' Test';
	}

	public function test2($app, $var) {
		return 'Number: '.$var;
	}

	public function test3($app, $var) {
		return 'String: '.$var;
	}

	public function home($app) {
		return $this->twig->render('home.twig');
	}
}