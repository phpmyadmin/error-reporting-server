<?php
App::uses('Notification', 'Model');

/**
 * Notification Test Case
 *
 */
class NotificationTest extends CakeTestCase {

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

/**
 * setUp method
 *
 * @return void
 */
	public function setUp() {
		parent::setUp();
		$this->Notification = ClassRegistry::init('Notification');
	}

	public function testAddNotifications() {
		$report_id = 2;
		$developer = new Developer();
		$devs = $developer->find('all');
		$res = Notification::addNotifications($report_id);
		$notifs = $this->Notification->find('all', array('conditions' => array('report_id' => $report_id)));

		$this->assertEquals(count($notifs), count($devs));
	}

/**
 * tearDown method
 *
 * @return void
 */
	public function tearDown() {
		unset($this->Notification);

		parent::tearDown();
	}

}
