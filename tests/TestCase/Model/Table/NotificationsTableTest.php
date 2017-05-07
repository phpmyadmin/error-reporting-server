<?php

namespace App\Test\TestCase\Model\Table;

use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * Notification Test Case.
 */
class NotificationsTableTest extends TestCase
{
    /**
     * Fixtures.
     *
     * @var array
     */
    public $fixtures = array(
        'app.notifications',
        'app.developers',
        'app.reports',
        'app.incidents',
    );

    /**
     * setUp method.
     */
    public function setUp()
    {
        parent::setUp();
        $this->Notifications = TableRegistry::get('Notifications');
    }

    public function testAddNotifications()
    {
        $report_id = 2;
        $developer = TableRegistry::get('Developers');
        $devs = $developer->find('all');
        $devs = $devs->hydrate(false)->toArray();
        $this->Notifications->addNotifications($report_id);
        $notifs = $this->Notifications->find('all', array('conditions' => array('report_id' => $report_id)));
        $notifs = $notifs->hydrate(false)->toArray();
        $this->assertEquals(count($notifs), count($devs));
    }
}
