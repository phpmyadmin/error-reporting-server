<?php

namespace App\Test\TestCase\Controller;

use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestCase;
use Cake\Network\Exception\NotFoundException;

class ReportsControllerTest extends IntegrationTestCase
{
    public $fixtures = array(
        'app.notifications',
        'app.developers',
        'app.reports',
        'app.incidents',
    );

    public function setUp()
    {
        $this->Reports = TableRegistry::get('Reports');
        $this->session(array('Developer.id' => 1));
    }

    public function testIndex()
    {
        $this->get('/reports');
        $this->assertEquals(array('3.8', '4.0'), $this->viewVariable('distinct_versions'));
        $this->assertEquals(array('forwarded', 'new'), $this->viewVariable('distinct_statuses'));
        $this->assertEquals(array('error1', 'error2'),
                $this->viewVariable('distinct_error_names'));
    }

    public function testView()
    {
        $this->get('/reports/view/1');

        $this->assertEquals(1, $this->viewVariable('report')[0]['id']);
        $this->assertEquals('error2', $this->viewVariable('report')[0]['error_name']);

        $this->assertNotEmpty($this->viewVariable('project_name'));
        $this->assertNotEmpty($this->viewVariable('columns'));

        $this->assertNotEmpty($this->viewVariable('related_entries'));
        $this->assertEquals(count($this->viewVariable('columns')),
                count($this->viewVariable('related_entries')));

        foreach ($this->viewVariable('columns') as $column) {
            $this->assertNotEmpty($this->viewVariable("${column}_distinct_count"));
        }

        $this->assertNotEmpty($this->viewVariable('incidents'));
        $this->assertEquals(1, count($this->viewVariable('incidents')));

        $this->assertNotEmpty($this->viewVariable('incidents_with_description'));
        $this->assertEquals(1, count($this->viewVariable('incidents_with_description')));

        $this->assertNotEmpty($this->viewVariable('incidents_with_stacktrace'));
        $this->assertEquals(1, count($this->viewVariable('incidents_with_stacktrace')));

        $this->assertNotEmpty($this->viewVariable('related_reports'));
        $this->assertEquals(1, count($this->viewVariable('related_reports')));

        $this->get('/reports/view/3');
        $this->assertResponseContains('Invalid Report');
        $this->assertResponseContains('/reports/view/3');
    }

    public function testDataTables()
    {
        $this->get('/reports/data_tables?sEcho=1&iDisplayLength=25');
        $expected = array(
            'iTotalRecords' => 4,
            'iTotalDisplayRecords' => 4,
            'sEcho' => 1,
            'aaData' => array(
                array("<input type='checkbox' name='reports[]' value='1'/>", 1, 'error2', 'Lorem ipsum dolor sit amet', 'filename_1.php', '4.0', 'Forwarded', 'js', '1'),
                array("<input type='checkbox' name='reports[]' value='2'/>", 2, 'error2', 'Lorem ipsum dolor sit amet', 'filename_2.php', '4.0', 'Forwarded', 'js', '1'),
                array("<input type='checkbox' name='reports[]' value='4'/>", 4, 'error1', 'Lorem ipsum dolor sit amet', 'filename_3.js', '3.8', 'Forwarded', 'js', '2'),
                array("<input type='checkbox' name='reports[]' value='5'/>", 5, 'error1', 'Lorem ipsum dolor sit amet', 'filename_4.js', '3.8', 'New', 'js', '1')
            ),
        );
        $this->assertEquals($expected, json_decode($this->_response->body(), true));

        $this->get('/reports/data_tables?sEcho=1&sSearch=error2&bSearchable_2=true&iSortCol_0=0&sSortDir_0=desc&bSortable_0=true&iSortingCols=2&iDisplayLength=25');
        $expected = array(
            'iTotalRecords' => 4,
            'iTotalDisplayRecords' => 2,
            'sEcho' => 1,
            'aaData' => array(
                array("<input type='checkbox' name='reports[]' value='1'/>", 1, 'error2', 'Lorem ipsum dolor sit amet', 'filename_1.php', '4.0', 'Forwarded', 'js', '1'),
                array("<input type='checkbox' name='reports[]' value='2'/>", 2, 'error2', 'Lorem ipsum dolor sit amet', 'filename_2.php', '4.0', 'Forwarded', 'js', '1'),
            ),
        );
        $result = json_decode($this->_response->body(), true);
        $this->assertEquals($expected, $result);

        $this->get('/reports/data_tables?sEcho=1&sSearch_1=1&iDisplayLength=25');
        $expected = array(
            'iTotalRecords' => 4,
            'iTotalDisplayRecords' => 1,
            'sEcho' => 1,
            'aaData' => array(
                array("<input type='checkbox' name='reports[]' value='1'/>", 1, 'error2', 'Lorem ipsum dolor sit amet', 'filename_1.php', '4.0', 'Forwarded', 'js', '1'),
            ),
        );
        $result = json_decode($this->_response->body(), true);
        $this->assertEquals($expected, $result);

        $this->get('/reports/data_tables?sEcho=1&sSearch_1=error&iDisplayLength=25');
        $expected = array(
            'iTotalRecords' => 4,
            'iTotalDisplayRecords' => 0,
            'sEcho' => 1,
            'aaData' => array(
            ),
        );
        $result = json_decode($this->_response->body(), true);
        $this->assertEquals($expected, $result);
    }

    public function testMarkRelatedTo() {
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

        $this->_testUnmarkRelatedTo();
    }


    /**
     * Don't run this as a separate test,
     * as the fixture tables are re-created and
     * we lose the previous related_to updations.
     *
     * So, call at the end of testMarkRelatedTo()
     */
    private function _testUnmarkRelatedTo() {
        $this->Reports->id = 2;
        $incidents = $this->Reports->getIncidents();
        $this->assertEquals(3, $incidents->count());

        $this->post('/reports/unmark_related_to/2');

        $this->Reports->id = 2;
        $incidents = $this->Reports->getIncidents();
        $this->assertEquals(1, $incidents->count());
    }
}
