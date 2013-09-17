<?php
App::uses('Sanitize', 'Utility');
class IncidentsControllerTest extends ControllerTestCase {

	public $fixtures = array('app.report', 'app.incident', 'app.developer');

	public function setUp() {
		PHPUnit_Framework_Error_Warning::$enabled = FALSE;
		PHPUnit_Framework_Error_Notice::$enabled = FALSE;
		$this->Incidents = $this->generate('Incidents', array(
			'components' => array(
				'Session',
			)
    ));
		$this->Incidents->Session
        ->expects($this->any())
        ->method('read')
				->will($this->returnValue(1));
		$this->Report = ClassRegistry::init('Report');
	}

	public function testView() {
		$result = $this->testAction('/incidents/view/1', array(
			'return' => 'vars'
		));

		$this->assertArrayHasKey('incident', $result);
		$this->assertInternalType('array',
				$result['incident']['Incident']['stacktrace']);
		$this->assertInternalType('array',
				$result['incident']['Incident']['full_report']);
	}

	public function testJson() {
		$result = $this->testAction('/incidents/json/1', array(
			'return' => 'contents'
		));
		$incident = json_decode($result, true);
		$expected = array(
			'Incident' => array(
				'id' => '1',
				'error_name' => 'Lorem ipsum dolor sit amet',
				'error_message' => 'Lorem ipsum dolor sit amet',
				'pma_version' => 'Lorem ipsum dolor sit amet',
				'php_version' => '5.5',
				'browser' => 'Lorem ipsum dolor sit amet',
				'user_os' => 'Lorem ipsum dolor sit amet',
				'server_software' => 'Lorem ipsum dolor sit amet',
				'stackhash' => 'hash',
				'configuration_storage' => 'Lorem ipsum dolor sit amet',
				'script_name' => 'Lorem ipsum dolor sit amet',
				'steps' => 'Lorem ipsum dolor sit amet',
				'stacktrace' => array(array()),
				'full_report' => array(),
				'different_stacktrace' => '1',
				'report_id' => '1',
				'created' => '2013-08-29 18:10:01',
				'modified' => '2013-08-29 18:10:01'
			)
		);

		$this->assertEquals($incident, $expected);
	}

	public function testCreate() {
		$bugReport = file_get_contents(TESTS . 'Fixture' . DS . "report.json");
		$bugReportDecoded = json_decode($bugReport, true);
		$bugReportDecoded = Sanitize::clean($bugReportDecoded);

		$result = $this->testAction('/incidents/create', array(
			'return' => 'contents',
			'method' => 'post',
			'data' => $bugReport,
		));

		$result = $this->testAction('/incidents/create', array(
			'return' => 'contents',
			'method' => 'post',
			'data' => $bugReport,
		));

		$report = $this->Report->find('first',
				array('order' => 'Report.created desc'));

		$this->assertArrayHasKey('Report', $report);
		$this->assertArrayHasKey('Incident', $report);
		$this->assertEquals(2, count($report['Incident']));
		$this->assertEquals($bugReportDecoded['exception']['message'],
				$report['Report']['error_message']);
		$this->assertEquals($bugReportDecoded['exception']['name'],
				$report['Report']['error_name']);
		$this->assertEquals($bugReportDecoded['pma_version'],
				$report['Report']['pma_version']);

		$result = $this->testAction('/incidents/create', array(
			'return' => 'contents',
			'method' => 'post',
		));
		$result = json_decode($result, true);
		$this->assertEquals(false, $result['success']);
	}
}
