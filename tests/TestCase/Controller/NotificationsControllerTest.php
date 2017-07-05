<?php

namespace App\Test\TestCase\Controller;

use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestCase;

/**
 * NotificationsController Test Case.
 */
class NotificationsControllerTest extends IntegrationTestCase
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

    public function setUp()
    {
        $this->Notifications = TableRegistry::get('Notifications');
        $this->session(array('Developer.id' => 1, 'read_only' => true));
    }

    public function testIndex()
    {
        $this->get('notifications');

        // 'read_only' users are not allowed to view notifications page
        $this->assertRedirect(['controller' => '', 'action' => 'index']);
    }

    public function testMassAction()
    {
        $this->session(array('Developer.id' => 1, 'read_only' => false));

        /* Test case 1 */
        $this->post('/notifications/mass_action',
            array('notifs' => array('1', '3'))
        );

        $notifications = $this->Notifications->find('all', array('fields' => array('Notifications.id')));
        $this->assertInstanceOf('Cake\ORM\Query', $notifications);
        $actual = $notifications->hydrate(false)->toArray();
        $expected = array(
            array(
                'id' => '2',
            ),
        );
        $this->assertEquals($actual, $expected);


        /* Test case 2 */
        $this->post('/notifications/mass_action',
            array('mark_all' => 1)
        );

        $notifications = $this->Notifications->find('all', array('fields' => array('Notifications.id')));
        $this->assertInstanceOf('Cake\ORM\Query', $notifications);
        $actual = $notifications->hydrate(false)->toArray();
        $expected = array();
        $this->assertEquals($actual, $expected);
    }
}
