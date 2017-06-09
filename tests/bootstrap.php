<?php
/**
 * Test runner bootstrap.
 *
 * Add additional configuration/setup your application needs when running
 * unit tests in this file.
 */

use Cake\Core\Configure;

require dirname(__DIR__) . '/config/bootstrap.php';

/*
 * Change default Email transport to 'test
 */
Configure::write('NotificationEmailsTransport', 'test');
