<?php

namespace App\Test\TestCase\Controller;

use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestCase;
use function count;
use function json_decode;

class ReportsControllerTest extends IntegrationTestCase
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
        $this->Reports = TableRegistry::getTableLocator()->get('Reports');
        $this->session(['Developer.id' => 1]);
    }

    public function testIndex(): void
    {
        $this->get('/reports');
        $this->assertEquals(['3.8', '4.0'], $this->viewVariable('distinct_versions'));
        $this->assertEquals(['forwarded', 'new'], $this->viewVariable('distinct_statuses'));
        $this->assertEquals(
            [
                'error1',
                'error2',
            ],
            $this->viewVariable('distinct_error_names')
        );
    }

    public function testView(): void
    {
        $this->get('/reports/view/1');

        $this->assertEquals(1, $this->viewVariable('report')[0]['id']);
        $this->assertEquals('error2', $this->viewVariable('report')[0]['error_name']);

        $this->assertNotEmpty($this->viewVariable('project_name'));
        $this->assertNotEmpty($this->viewVariable('columns'));

        $this->assertNotEmpty($this->viewVariable('related_entries'));
        $this->assertEquals(
            count($this->viewVariable('columns')),
            count($this->viewVariable('related_entries'))
        );

        foreach ($this->viewVariable('columns') as $column) {
            $this->assertNotEmpty($this->viewVariable("${column}_distinct_count"));
        }

        $this->assertNotEmpty($this->viewVariable('incidents'));
        $this->assertEquals(1, count($this->viewVariable('incidents')));

        $this->assertNotEmpty($this->viewVariable('incidents_with_description'));
        $this->assertEquals(1, count($this->viewVariable('incidents_with_description')->toList()));

        $this->assertNotEmpty($this->viewVariable('incidents_with_stacktrace'));
        $this->assertEquals(1, count($this->viewVariable('incidents_with_stacktrace')->toList()));

        $this->assertNotEmpty($this->viewVariable('related_reports'));
        //FIXME: 0 or 1 ?
        //$this->assertEquals(1, count($this->viewVariable('related_reports')->toList()));

        $this->get('/reports/view/3');
        $this->assertResponseContains('Invalid Report');
        $this->assertResponseContains('/reports/view/3');
    }

    public function testDataTables(): void
    {
        $this->get('/reports/data_tables?sEcho=1&iDisplayLength=25');
        $expected = [
            'iTotalRecords' => 4,
            'iTotalDisplayRecords' => 4,
            'sEcho' => 1,
            'aaData' => [
                [
                    "<input type='checkbox' name='reports[]' value='1'/>",
                    1,
                    'error2',
                    'Lorem ipsum dolor sit amet',
                    'filename_1.php',
                    '4.0',
                    'Forwarded',
                    'js',
                    '1',
                ],
                [
                    "<input type='checkbox' name='reports[]' value='2'/>",
                    2,
                    'error2',
                    'Lorem ipsum dolor sit amet',
                    'filename_2.php',
                    '4.0',
                    'Forwarded',
                    'js',
                    '1',
                ],
                [
                    "<input type='checkbox' name='reports[]' value='4'/>",
                    4,
                    'error1',
                    'Lorem ipsum dolor sit amet',
                    'filename_3.js',
                    '3.8',
                    'Forwarded',
                    'js',
                    '2',
                ],
                [
                    "<input type='checkbox' name='reports[]' value='5'/>",
                    5,
                    'error1',
                    'Lorem ipsum dolor sit amet',
                    'filename_4.js',
                    '3.8',
                    'New',
                    'js',
                    '1',
                ],
            ],
        ];
        $this->assertEquals($expected, json_decode($this->_response->body(), true));

        $this->get('/reports/data_tables?sEcho=1&sSearch=error2&bSearchable_2=true&iSortCol_0=0&sSortDir_0=desc&bSortable_0=true&iSortingCols=2&iDisplayLength=25');
        $expected = [
            'iTotalRecords' => 4,
            'iTotalDisplayRecords' => 2,
            'sEcho' => 1,
            'aaData' => [
                [
                    "<input type='checkbox' name='reports[]' value='1'/>",
                    1,
                    'error2',
                    'Lorem ipsum dolor sit amet',
                    'filename_1.php',
                    '4.0',
                    'Forwarded',
                    'js',
                    '1',
                ],
                [
                    "<input type='checkbox' name='reports[]' value='2'/>",
                    2,
                    'error2',
                    'Lorem ipsum dolor sit amet',
                    'filename_2.php',
                    '4.0',
                    'Forwarded',
                    'js',
                    '1',
                ],
            ],
        ];
        $result = json_decode($this->_response->body(), true);
        $this->assertEquals($expected, $result);

        $this->get('/reports/data_tables?sEcho=1&sSearch_1=1&iDisplayLength=25');
        $expected = [
            'iTotalRecords' => 4,
            'iTotalDisplayRecords' => 1,
            'sEcho' => 1,
            'aaData' => [
                [
                    "<input type='checkbox' name='reports[]' value='1'/>",
                    1,
                    'error2',
                    'Lorem ipsum dolor sit amet',
                    'filename_1.php',
                    '4.0',
                    'Forwarded',
                    'js',
                    '1',
                ],
            ],
        ];
        $result = json_decode($this->_response->body(), true);
        $this->assertEquals($expected, $result);

        $this->get('/reports/data_tables?sEcho=1&sSearch_1=0&iDisplayLength=25');
        $expected = [
            'iTotalRecords' => 4,
            'iTotalDisplayRecords' => 0,
            'sEcho' => 1,
            'aaData' => [],
        ];
        $result = json_decode($this->_response->body(), true);
        $this->assertEquals($expected, $result);
    }

    public function testMarkRelatedTo(): void
    {
        $this->Reports->id = 2;
        $incidents = $this->Reports->getIncidents();
        $this->assertEquals(1, $incidents->count());

        $this->post(
            '/reports/mark_related_to/2',
            ['related_to' => 4]
        );

        $this->Reports->id = 2;
        $incidents = $this->Reports->getIncidents();
        $this->assertEquals(3, $incidents->count());
    }

    /**
     * @depends testMarkRelatedTo
     * @return void nothing
     */
    public function testUnmarkRelatedTo(): void
    {
        $this->testMarkRelatedTo();
        $this->Reports->id = 2;
        $incidents = $this->Reports->getIncidents();
        $this->assertEquals(3, $incidents->count());

        $this->post('/reports/unmark_related_to/2');

        $this->Reports->id = 2;
        $incidents = $this->Reports->getIncidents();
        $this->assertEquals(1, $incidents->count());
    }

    /**
     * Test for 'mass_action' action
     */
    public function testMassAction(): void
    {
        $report1 = $this->Reports->get(1);
        $this->assertEquals('forwarded', $report1->status);

        $report5 = $this->Reports->get(5);
        $this->assertEquals('new', $report5->status);

        /* Test case 1: Incorrect state */
        $this->post(
            '/reports/mass_action',
            [
                'reports' => [
                    '1',
                    '5',
                ],
                'state' => 'incorrect_state',
            ]
        );

        // Should not change
        $report1 = $this->Reports->get(1);
        $this->assertEquals('forwarded', $report1->status);

        $report5 = $this->Reports->get(5);
        $this->assertEquals('new', $report5->status);

        /* Test case 2: No reports selected */
        $this->post(
            '/reports/mass_action',
            [
                'reports' => [],
                'state' => 'resolved',
            ]
        );

        // Should not change
        $report1 = $this->Reports->get(1);
        $this->assertEquals('forwarded', $report1->status);

        $report5 = $this->Reports->get(5);
        $this->assertEquals('new', $report5->status);

        /* Test case 3: Invalid report id passed */
        $this->post(
            '/reports/mass_action',
            [
                'reports' => [10],
                'state' => 'resolved',
            ]
        );

        /* Test case 4 */
        $this->post(
            '/reports/mass_action',
            [
                'reports' => [
                    1,
                    5,
                ],
                'state' => 'resolved',
            ]
        );

        // Should change
        $report1 = $this->Reports->get(1);
        $this->assertEquals('resolved', $report1->status);

        $report5 = $this->Reports->get(5);
        $this->assertEquals('resolved', $report5->status);
    }

    /**
     * Test for 'change_state' action
     */
    public function testChangeState(): void
    {
        $this->session(['Developer.id' => 1, 'read_only' => false]);

        $report = $this->Reports->get(1);
        $this->assertEquals('forwarded', $report->status);

        /* Test case 1: Incorrect Report ID */
        $this->post(
            '/reports/change_state/6',
            ['state' => 'resolved']
        );

        /* Test case 2: Incorrect State */
        $this->post(
            '/reports/change_state/1',
            ['state' => 'incorrect_state']
        );
        $report = $this->Reports->get(1);
        $this->assertEquals('forwarded', $report->status);

        /* Test case 3 */
        $this->post(
            '/reports/change_state/1',
            ['state' => 'resolved']
        );
        $report = $this->Reports->get(1);
        $this->assertEquals('resolved', $report->status);
    }
}
