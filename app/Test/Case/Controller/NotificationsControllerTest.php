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
				'Developer' => array(),
				'Report' => array()
			),
		);

		$this->assertEquals($actual, $expected);
	}
}
