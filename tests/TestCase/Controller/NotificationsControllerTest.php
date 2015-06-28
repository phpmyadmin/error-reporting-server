<?php
namespace App\Test\TestCase\Controller;

use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestCase;

/**
 * NotificationsController Test Case
 *
 */
class NotificationsControllerTest extends IntegrationTestCase {

/**
 * Fixtures
 *
 * @var array
 */
	public $fixtures = array(
		'app.notifications',
		'app.developers',
		'app.reports',
        'app.incidents'
	);

	public function setUp() {
		$this->Notifications = TableRegistry::get('Notifications');
		$this->session(['Developer.id' => 1]);
	}

	public function testMassAction() {
		$this->post('/notifications/mass_action',
            array('notifs' => array('1','3'))
		);

		$notifications = $this->Notifications->find('all', array('fields' => array('Notifications.id')));
        $this->assertInstanceOf('Cake\ORM\Query', $notifications);
        $actual = $notifications->hydrate(false)->toArray();
		$expected = array(
			array(
                'id' => "2"
            )
        );
		$this->assertEquals($actual, $expected);
	}

	public function testCleanOldNotifs() {
		// Mark one Notification as "not older". Setting 'created' to current time.
		$notification = $this->Notifications->get(3);
		$notification->created= date('Y-m-d H:i:s', time());
		$this->Notifications->save($notification);

		// define constant for Cron Jobs
		if (!defined('CRON_DISPATCHER')) {
			define('CRON_DISPATCHER', true);
		}
		$this->get('/notifications/clean_old_notifs');
		$notifications = $this->Notifications->find('all', array('fields' => array('Notifications.id')));
        $this->assertInstanceOf('Cake\ORM\Query', $notifications);
        $actual = $notifications->hydrate(false)->toArray();
		$expected = array(
			array(
				'id' => "3"
            )
		);

		$this->assertEquals($actual, $expected);
	}
}
