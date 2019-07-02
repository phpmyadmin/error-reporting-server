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
    public $fixtures = [
        'app.Notifications',
        'app.Developers',
        'app.Reports',
        'app.Incidents',
    ];

    /**
     * setUp method.
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $this->Notifications = TableRegistry::getTableLocator()->get('Notifications');
    }

    public function testAddNotifications()
    {
        $report_id = 2;
        $developer = TableRegistry::getTableLocator()->get('Developers');
        $devs = $developer->find('all');
        $devs = $devs->hydrate(false)->toArray();
        $this->Notifications->addNotifications($report_id);
        $notifs = $this->Notifications->find('all', ['conditions' => ['report_id' => $report_id]]);
        $notifs = $notifs->hydrate(false)->toArray();
        $this->assertEquals(count($notifs), count($devs));
    }
}
