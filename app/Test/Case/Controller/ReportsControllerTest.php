<?php
class ReportsControllerTest extends ControllerTestCase {

	public $fixtures = array('app.report', 'app.incident', 'app.developer');

	public function setUp() {
		$this->Reports = $this->generate('Reports');
		$Session = new SessionComponent(new ComponentCollection());
		$Session->write("Developer.id", 1);
	}

	public function testIndex() {
		$result = $this->testAction('/reports', array(
			'return' => 'vars'
		));

		$this->assertEquals(array('4.0', '3.8'), $this->vars['distinct_versions']);
		$this->assertEquals(array('new'), $this->vars['distinct_statuses']);
		$this->assertEquals(array('error2', 'error1'),
				$this->vars['distinct_error_names']);
	}

	public function testView() {
		$result = $this->testAction('/reports/view/1', array(
			'return' => 'vars'
		));

		$this->assertEquals(1, $this->vars['report']['Report']['id']);
		$this->assertEquals('error2', $this->vars['report']['Report']['error_name']);

		$this->assertArrayHasKey('project_name', $this->vars);
		$this->assertArrayHasKey('columns', $this->vars);

		$this->assertArrayHasKey('related_entries', $this->vars);
		$this->assertEquals(count($this->vars['columns']),
				count($this->vars['related_entries']));

		foreach ($this->vars['columns'] as $column) {
			$this->assertArrayHasKey("${column}_distinct_count", $this->vars);
		}

		$this->assertArrayHasKey('incidents', $this->vars);
		$this->assertEquals(3, count($this->vars['incidents']));

		$this->assertArrayHasKey('incidents_with_description', $this->vars);
		$this->assertEquals(2, count($this->vars['incidents_with_description']));

		$this->assertArrayHasKey('incidents_with_stacktrace', $this->vars);
		$this->assertEquals(2, count($this->vars['incidents_with_stacktrace']));

		$this->assertArrayHasKey('related_reports', $this->vars);
		$this->assertEquals(1, count($this->vars['related_reports']));

		$this->setExpectedException('NotFoundException');
		$result = $this->testAction('/reports/view/3', array(
			'return' => 'vars'
		));

		$this->setExpectedException('NotFoundException');
		$result = $this->testAction('/reports/view', array(
			'return' => 'vars'
		));
	}

	public function testDataTables() {
		$this->testAction('/reports/data_tables', array(
			'data' => array(
				'sEcho' => 1,
			),
			'method' => 'get',
			'return' => 'view'
		));
		$expected = array(
			'iTotalRecords' => 3,
			'iTotalDisplayRecords' => 3,
			'sEcho' => 1,
			'aaData' => array(
				array('1', 'error2', 'Lorem ipsum dolor sit amet', '4.0', 'new'),
				array('2', 'error2', 'Lorem ipsum dolor sit amet', '4.0', 'new'),
				array('4', 'error1', 'Lorem ipsum dolor sit amet', '3.8', 'new')
			)
		);
		$result = json_decode($this->contents, true);
		$this->assertEquals($expected, $result);

		$this->testAction('/reports/data_tables', array(
			'data' => array(
				'sEcho' => 1,
				'sSearch' => 'error2',
				'bSearchable_1' => 'true',
				'iSortCol_0' => '0',
				'sSortDir_0' => 'desc',
				'bSortable_0' => 'true',
				'iSortingCols' => '1',
			),
			'method' => 'get',
			'return' => 'view'
		));
		$expected = array(
			'iTotalRecords' => 3,
			'iTotalDisplayRecords' => 2,
			'sEcho' => 1,
			'aaData' => array(
				array('2', 'error2', 'Lorem ipsum dolor sit amet', '4.0', 'new'),
				array('1', 'error2', 'Lorem ipsum dolor sit amet', '4.0', 'new'),
			)
		);
		$result = json_decode($this->contents, true);
		$this->assertEquals($expected, $result);

		$this->testAction('/reports/data_tables', array(
			'data' => array(
				'sEcho' => 1,
				'sSearch_0' => '1',
			),
			'method' => 'get',
			'return' => 'view'
		));
		$expected = array(
			'iTotalRecords' => 3,
			'iTotalDisplayRecords' => 1,
			'sEcho' => 1,
			'aaData' => array(
				array('1', 'error2', 'Lorem ipsum dolor sit amet', '4.0', 'new'),
			)
		);
		$result = json_decode($this->contents, true);
		$this->assertEquals($expected, $result);

		$this->testAction('/reports/data_tables', array(
			'data' => array(
				'sEcho' => 1,
				'sSearch_1' => 'error',
			),
			'method' => 'get',
			'return' => 'view'
		));
		$expected = array(
			'iTotalRecords' => 3,
			'iTotalDisplayRecords' => 0,
			'sEcho' => 1,
			'aaData' => array(
			)
		);
		$result = json_decode($this->contents, true);
		$this->assertEquals($expected, $result);
	}

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
	}
}
