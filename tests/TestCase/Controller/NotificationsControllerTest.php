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

    public function testDataTables()
    {
        $this->session(array('Developer.id' => 1, 'read_only' => false));

        $this->get('notifications/data_tables?sEcho=1&iDisplayLength=25');

        $expected = array(
            'iTotalRecords' => 2,
            'iTotalDisplayRecords' => 2,
            'sEcho' => 1,
            'aaData' => array(
                array('<input type="checkbox" name="notifs[]" value="1"/>', '<a href="/reports/view/1">1</a>', 'error2', 'Lorem ipsum dolor sit amet', '4.0', 'js', '2014-01-01T07:05:09'),
                array('<input type="checkbox" name="notifs[]" value="2"/>', '<a href="/reports/view/4">4</a>', 'error1', 'Lorem ipsum dolor sit amet', '3.8', 'js', '2014-01-02T07:05:09'),
            ),
        );

        $this->assertResponseOk();
        $this->assertEquals($expected, json_decode($this->_response->body(), true));
    }
}
