<?php
namespace App\Test\TestCase\Controller;

use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestCase;

class ReportsControllerTest extends IntegrationTestCase {

	public $fixtures = array(
		'app.notifications',
		'app.developers',
		'app.reports',
        'app.incidents'
	);

	public function setUp() {
		$this->Reports = TableRegistry::get('Reports');
        $this->session(['Developer.id' => 1]);
	}

	public function testIndex() {
		$this->get('/reports');
        $this->assertEquals(array('3.8', '4.0'), $this->viewVariable('distinct_versions'));
		$this->assertEquals(array('new'), $this->viewVariable('distinct_statuses'));
		$this->assertEquals(array('error1', 'error2'),
				$this->viewVariable('distinct_error_names'));
	}

	public function testView() {
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
       // $this->assertResponseOk();
		//$this->setExpectedException('NotFoundException');
    	//	$this->get('/reports/view');
	}

	public function testDataTables() {
		$this->get('/reports/data_tables?sEcho=1&iDisplayLength=25');
		$expected = array(
			'iTotalRecords' => 3,
			'iTotalDisplayRecords' => 3,
			'sEcho' => 1,
			'aaData' => array(
				array("<input type='checkbox' name='reports[]' value='1'/>", 1, 'error2', 'Lorem ipsum dolor sit amet', '4.0', 'New', 'js', '1'),
				array("<input type='checkbox' name='reports[]' value='2'/>", 2, 'error2', 'Lorem ipsum dolor sit amet', '4.0', 'New', 'js', '1'),
                array("<input type='checkbox' name='reports[]' value='4'/>", 4, 'error1', 'Lorem ipsum dolor sit amet', '3.8', 'New', 'js', '2')

			)
		);
		$this->assertEquals($expected, json_decode($this->_response->body(), true));

		$this->get('/reports/data_tables?sEcho=1&sSearch=error2&bSearchable_2=true&iSortCol_0=0&sSortDir_0=desc&bSortable_0=true&iSortingCols=2&iDisplayLength=25');
		$expected = array(
			'iTotalRecords' => 3,
			'iTotalDisplayRecords' => 2,
			'sEcho' => 1,
			'aaData' => array(
				array("<input type='checkbox' name='reports[]' value='1'/>", 1, 'error2', 'Lorem ipsum dolor sit amet', '4.0', 'New', 'js', '1'),
				array("<input type='checkbox' name='reports[]' value='2'/>", 2, 'error2', 'Lorem ipsum dolor sit amet', '4.0', 'New', 'js', '1'),
			)
		);
		$result = json_decode($this->_response->body(), true);
		$this->assertEquals($expected, $result);

		$this->get('/reports/data_tables?sEcho=1&sSearch_1=1&iDisplayLength=25');
		$expected = array(
			'iTotalRecords' => 3,
			'iTotalDisplayRecords' => 1,
			'sEcho' => 1,
			'aaData' => array(
				array("<input type='checkbox' name='reports[]' value='1'/>", '1', 'error2', 'Lorem ipsum dolor sit amet', '4.0', 'New', 'js'),
			)
		);
		$result = json_decode($this->_response->body(), true);
		$this->assertEquals($expected, $result);

		$this->get('/reports/data_tables?sEcho=1&sSearch_1=error&iDisplayLength=25');
		$expected = array(
			'iTotalRecords' => 3,
			'iTotalDisplayRecords' => 0,
			'sEcho' => 1,
			'aaData' => array(
			)
		);
		$result = json_decode($this->_response->body(), true);
		$this->assertEquals($expected, $result);
	}

/*
 * TODO: Will do after fix related to feature.
	public function testMarkRelatedTo() {
		$this->Reports->Report->read(null, 2);
		$incidents = $this->Reports->Report->getIncidents();
		$this->assertEquals(0, count($incidents));

		$this->testAction('/reports/mark_related_to/2', array(
			'data' => array(
				'related_to' => 4,
			),
			'method' => 'get',
			'return' => 'view'
		));

		$this->Reports->Report->read(null, 2);
		$incidents = $this->Reports->Report->getIncidents();
		$this->assertEquals(3, count($incidents));
	}

	public function testUnmarkRelatedTo() {
		$this->Reports->Report->read(null, 1);
		$incidents = $this->Reports->Report->getIncidents();
		$this->assertEquals(3, count($incidents));

		$this->testAction('/reports/unmark_related_to/1', array(
			'data' => array(
			),
			'method' => 'get',
			'return' => 'view'
		));

		$this->Reports->Report->read(null, 1);
		$incidents = $this->Reports->Report->getIncidents();
		$this->assertEquals(1, count($incidents));
	}*/
}
