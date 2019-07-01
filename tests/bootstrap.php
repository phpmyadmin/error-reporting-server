<?php
/**
 * Test runner bootstrap.
 *
 * Add additional configuration/setup your application needs when running
 * unit tests in this file.
 */
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/config/bootstrap.php';

use Cake\Core\Configure;

$_SERVER['PHP_SELF'] = '/';

/*
 * Change default Email transport to 'test
 */
Configure::write('NotificationEmailsTransport', 'test');
