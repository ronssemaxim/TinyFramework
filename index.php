<?php
include 'includes/TinyFramework.php';

date_default_timezone_set('Europe/Brussels');

$fm = new TinyFramework(array(
		'/home' => 'HomeController',
		'/auth' => 'AuthController',
		'default' => '/home'
	), array(
		'debug' => true
	)); // set to false on production

$fm->debug = true;

$db = $fm->AddDb(array(
	'name' => 'Test',
	'driver' => 'mysql',
	'host' => 'localhost', 
	'db' => 'database',
	'user' => 'username',
	'pass' => 'password'
)); // add DB connection (required)


// add db tables (optional), if user is trying to access a table that was not added in this file, 
// the code will automatically try to add the table. 
// @see IDBTable.php for default functions; @see DB.php, function GetTable for details about the auto-creation
$db->AddTable('mysqlTable', 'TestTable');

$fm->Run(); // finally, run the framework
?>