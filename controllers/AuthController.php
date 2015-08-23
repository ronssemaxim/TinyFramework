<?php
require_once 'includes/validator.php';
require_once 'includes/IController.php';
require_once 'includes/form.php';
use \Kal\data;

class AuthController extends BaseController implements IController {
	function __construct($app) {
		$this->Routes($app, array(
			'/login' => array(
				'function' => 'login',
				'name' => 'auth.login'
			),
			'/logout' => array(
				'function' => 'logout',
				'name' => 'auth.logout',
				'checkBefore' => 'IsLoggedIn'
			),
			'default' => 'login',
			'onCheckFailure' => array(
				'redirect' => 'home'
			)
		));
	}

	public function logout($app) {
		return $this->twig->render('auth/loggedOut.twig');
	}

	public function login($app) {
		$form = new Form();
		$el = $form->addElement('username', Form::TEXT, 'Username');
		$el->min = 3;
		$el->max = 8;
		$el->notEmtpy = true;

		$el = $form->addElement('password', Form::PASSWORD, 'Password');
		$el->min = 3;
		$el->max = 8;
		$el->notEmtpy = true;

		$el = $form->addElement('submit', Form::SUBMIT, '', 'Submit');

		if($form->isValid()) {
			$vals = $form->getValues();

			// var_dump($app->GetDb('Test')->GetTable('mysqlTable')->get(array('id' => 1))); ==> use custom DB Table class (TestDB.php)
			var_dump($app->GetDb('Test')->GetTable('testtable')->get(array('id' => 1))); // ==> use auto genereted baseclass, dynamically adds tables
			/*
			if($password != $thevaluefromthedb) {
				$form->addError('password', 'too many failed login attempts. Try again later!');
				$form->addNotice('password', 'too many failed login attempts. Try again later!');
			}
			// else log the user in and show logged in template
			*/
		}

		return $this->twig->render('auth/login.twig', array(
			'form' => $form
		));
	}
}