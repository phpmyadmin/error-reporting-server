<?php
namespace app\Test\Case\Model;

use App\Model\Notification;
use Cake\TestSuite\TestCase;

/**
 * Notification Test Case
 *
 */
class NotificationTest extends TestCase {

/**
 * Fixtures
 *
 * @var array
 */
	public $fixtures = array(
		'app.notifications',
		'app.developers',
		'app.reports'
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
		Notification::addNotifications($report_id);
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
