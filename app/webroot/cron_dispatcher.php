<?php
/**
 * Cron Dispatcher
 *
 * The dispatcher for cron jobs. 
 * Use to invoke some controller via cron.
 *
 */

/**
 * Use the DS to separate the directories in other defines
 */
if (!defined('DS')) {
	define('DS', DIRECTORY_SEPARATOR);
}

/**
 * These defines should only be edited if you have cake installed in
 * a directory layout other than the way it is distributed.
 * When using custom settings be sure to use the DS and do not add a trailing DS.
 */

/**
 * The full path to the directory which holds "app", WITHOUT a trailing DS.
 *
 */
if (!defined('ROOT')) {
	define('ROOT', dirname(dirname(dirname(__FILE__))));
}

/**
 * The actual directory name for the "app".
 *
 */
if (!defined('APP_DIR')) {
	define('APP_DIR', basename(dirname(dirname(__FILE__))));
}

/**
 * The absolute path to the "cake" directory, WITHOUT a trailing DS.
 *
 * Un-comment this line to specify a fixed path to CakePHP.
 * This should point at the directory containing `Cake`.
 *
 * For ease of development CakePHP uses PHP's include_path. If you
 * cannot modify your include_path set this value.
 *
 * Leaving this constant undefined will result in it being defined in Cake/bootstrap.php
 *
 * The following line differs from its sibling
 * /lib/Cake/Console/Templates/skel/webroot/index.php
 */
//define('CAKE_CORE_INCLUDE_PATH', ROOT . DS . 'lib');

/**
 * Editing below this line should NOT be necessary.
 * Change at your own risk.
 *
 */
if (!defined('WEBROOT_DIR')) {
	define('WEBROOT_DIR', basename(dirname(__FILE__)));
}
if (!defined('WWW_ROOT')) {
	define('WWW_ROOT', dirname(__FILE__) . DS);
}

// for built-in server
if (php_sapi_name() === 'cli-server') {
	if ($_SERVER['REQUEST_URI'] !== '/' && file_exists(WWW_ROOT . $_SERVER['PHP_SELF'])) {
		return false;
	}
	$_SERVER['PHP_SELF'] = '/' . basename(__FILE__);
}

if (!defined('CAKE_CORE_INCLUDE_PATH')) {
	if (function_exists('ini_set')) {
		ini_set('include_path', ROOT . DS . 'lib' . PATH_SEPARATOR . ini_get('include_path'));
	}
	if (!include 'Cake' . DS . 'bootstrap.php') {
		$failed = true;
	}
} else {
	if (!include CAKE_CORE_INCLUDE_PATH . DS . 'Cake' . DS . 'bootstrap.php') {
		$failed = true;
	}
}
if (!empty($failed)) {
	trigger_error("CakePHP core could not be found. Check the value of CAKE_CORE_INCLUDE_PATH in APP/webroot/index.php. It should point to the directory containing your " . DS . "cake core directory and your " . DS . "vendors root directory.", E_USER_ERROR);
}

App::uses('Dispatcher', 'Routing');
$Dispatcher= new Dispatcher();

if ((getcwd() == dirname(__FILE__))) {
	// to stop attempts to execute this script directly
	// header() is used because Router::redirect('/') is not working here!!
	header('Location:https://reports.phpmyadmin.net');
}

if ($argc < 2) {
	die(
		"FATAL ERROR: Not enough arguments!!"
		. "\nCorrect Usage:<php interpreter> app/webroot/cron_dispatcher.php <target>\n"
	);
}

CakeLog::write(
	'cron_jobs',
	'STARTED: Job"'
		. $argv[1]
		. '" at '
		. date('d-m-Y G:i:s (e)'),
	'cron_jobs'
);

define('CRON_DISPATCHER', true);
$Dispatcher->dispatch(
	new CakeRequest($argv[1]),
	new CakeResponse()
);

CakeLog::write(
	'cron_jobs',
	'FINISHED: Job"'
		. $argv[1]
		. '" at '
		. date('d-m-Y G:i:s (e)'),
	'cron_jobs'
);