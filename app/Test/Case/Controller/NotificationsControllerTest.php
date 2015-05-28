<?php
App::uses('NotificationsController', 'Controller');
App::uses('Notification', 'Model');

/**
 * NotificationsController Test Case
 *
 */
class NotificationsControllerTest extends ControllerTestCase {

/**
 * Fixtures
 *
 * @var array
 */
	public $fixtures = array(
		'app.notification',
		'app.developer',
		'app.report'
	);

	public function setUp() {
		$this->Notification = $this->generate('Notifications');
		$Session = new SessionComponent(new ComponentCollection());
		$Session->write("Developer.id", 1);
	}

	public function testMassAction() {
		$this->testAction('/notifications/mass_action',
			array(
				'data' => array('notifs' => array('1','3')),
				'method' => 'post'
			)
		);

		$notification = new Notification();
		$actual = $notification->find('all', array('fields' => array('Notification.id')));
		$expected = array(
			array(
				'Notification'=> array(
					'id' => "2"
				),
				'Report' => array()
			),
		);

		$this->assertEquals($actual, $expected);
	}

	public function testCleanOldNotifs() {
		// Mark one Notification as "not older". Setting 'created' to current time.
		$notification = new Notification();
		$notification->read(null, 3);
		$notification->set('created', date('Y-m-d H:i:s', time()));
		$notification->save();

		// define constant for Cron Jobs
		if (!defined('CRON_DISPATCHER')) {
			define('CRON_DISPATCHER', true);
		}
		$this->testAction('/notifications/clean_old_notifs');
		$actual = $notification->find('all', array('fields' => array('Notification.id')));
		$expected = array(
			array(
				'Notification'=> array(
					'id' => "3"
				),
				'Report' => array()
			),
		);

		$this->assertEquals($actual, $expected);
	}
}
