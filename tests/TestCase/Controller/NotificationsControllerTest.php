<?php

namespace App\Test\TestCase\Controller;

use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestCase;
use function json_decode;

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
    public $fixtures = [
        'app.Notifications',
        'app.Developers',
        'app.Reports',
        'app.Incidents',
    ];

    public function setUp(): void
    {
        $this->Notifications = TableRegistry::getTableLocator()->get('Notifications');
        $this->session(['Developer.id' => 1, 'read_only' => true]);
    }

    public function testIndex(): void
    {
        $this->get('notifications');

        // 'read_only' users are not allowed to view notifications page
        $this->assertRedirect(['controller' => '', 'action' => 'index']);
    }

    public function testMassAction(): void
    {
        $this->session(['Developer.id' => 1, 'read_only' => false]);

        /* Test case 1 */
        $this->post(
            '/notifications/mass_action',
            [
                'notifs' => [
                    '1',
                    '3',
                ],
            ]
        );

        $notifications = $this->Notifications->find('all', ['fields' => ['Notifications.id']]);
        $this->assertInstanceOf('Cake\ORM\Query', $notifications);
        $actual = $notifications->hydrate(false)->toArray();
        $expected = [
            ['id' => '2'],
        ];
        $this->assertEquals($actual, $expected);

        /* Test case 2 */
        $this->post(
            '/notifications/mass_action',
            ['mark_all' => 1]
        );

        $notifications = $this->Notifications->find('all', ['fields' => ['Notifications.id']]);
        $this->assertInstanceOf('Cake\ORM\Query', $notifications);
        $actual = $notifications->hydrate(false)->toArray();
        $expected = [];
        $this->assertEquals($actual, $expected);
    }

    public function testDataTables(): void
    {
        $this->session(['Developer.id' => 1, 'read_only' => false]);

        $this->get('notifications/data_tables?sEcho=1&iDisplayLength=25');

        $expected = [
            'iTotalRecords' => 2,
            'iTotalDisplayRecords' => 2,
            'sEcho' => 1,
            'aaData' => [
                [
                    '<input type="checkbox" name="notifs[]" value="1"/>',
                    '<a href="/reports/view/1">1</a>',
                    'error2',
                    'Lorem ipsum dolor sit amet',
                    '4.0',
                    'js',
                    '2014-01-01T07:05:09+00:00',
                ],
                [
                    '<input type="checkbox" name="notifs[]" value="2"/>',
                    '<a href="/reports/view/4">4</a>',
                    'error1',
                    'Lorem ipsum dolor sit amet',
                    '3.8',
                    'js',
                    '2014-01-02T07:05:09+00:00',
                ],
            ],
        ];

        $this->assertResponseOk();
        $this->assertEquals($expected, json_decode($this->_response->body(), true));
    }
}
