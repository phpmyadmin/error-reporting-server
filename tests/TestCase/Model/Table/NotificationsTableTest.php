<?php

namespace App\Test\TestCase\Model\Table;

use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use function count;

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
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->Notifications = TableRegistry::getTableLocator()->get('Notifications');
    }

    public function testAddNotifications(): void
    {
        $report_id = 2;
        $developer = TableRegistry::getTableLocator()->get('Developers');
        $devs = $developer->find('all');
        $devs = $devs->enableHydration(false)->toArray();
        $this->Notifications->addNotifications($report_id);
        $notifs = $this->Notifications->find('all', ['conditions' => ['report_id' => $report_id]]);
        $notifs = $notifs->enableHydration(false)->toArray();
        $this->assertEquals(count($notifs), count($devs));
    }
}
