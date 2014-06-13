<?php
App::uses('Controller', 'Controller');
App::uses('View', 'View');
App::uses('Incident', 'Model');

class IncidentTest extends CakeTestCase {

	public function setUp() {
		parent::setUp();
		$this->Incident = ClassRegistry::init('Incident');
	}

	public function testGetStackHash() {
		$method = new ReflectionMethod('Incident', 'getStackHash');
		$method->setAccessible(true);

		$stacktrace1 = array(
			array(
				'filename' => 'file1',
				'line' => 300,
			),
			array(
				'filename' => 'file2',
				'line' => 200,
			)
		);

		$stacktrace2 = array(
			array(
				'line' => 300,
				'filename' => 'file1',
			),
			array(
				'line' => 200,
				'filename' => 'file2',
			)
		);

		$result = $method->invoke($this->Incident,
				$stacktrace1);
		$this->assertEquals("a441639902837d88db25214812c0cd83", $result);

		$result = $method->invoke($this->Incident,
				$stacktrace2);
		$this->assertEquals("a441639902837d88db25214812c0cd83", $result);
	}

	public function testGetServer() {
		$method = new ReflectionMethod('Incident', '_getServer');
		$method->setAccessible(true);

		$result = $method->invoke($this->Incident,
				"some random/data Apache/2.1.7;other.random/data");
		$this->assertEquals("Apache/2.1", $result);

		$result = $method->invoke($this->Incident,
				"some random/data Nginx/1.0.7;other.random/data");
		$this->assertEquals("Nginx/1.0", $result);

		$result = $method->invoke($this->Incident,
				"some random/data Lighttpd/4.6.7;other.random/data");
		$this->assertEquals("Lighttpd/4.6", $result);

		$result = $method->invoke($this->Incident,
				"some random/data DoesNotExist/3.5;other.random/data");
		$this->assertEquals("UNKNOWN", $result);
	}

	public function testGetSimpleVersion() {
		$method = new ReflectionMethod('Incident', '_getSimpleVersion');
		$method->setAccessible(true);

		$result = $method->invoke($this->Incident,
				"15.3.12.17", 1);
		$this->assertEquals("15", $result);

		$result = $method->invoke($this->Incident,
				"15.3.12.17", 2);
		$this->assertEquals("15.3", $result);

		$result = $method->invoke($this->Incident,
				"15.3.12.17", 3);
		$this->assertEquals("15.3.12", $result);

		$result = $method->invoke($this->Incident,
				"15.3.12.17", "wrong argument");
		$this->assertEquals("15", $result);

		$result = $method->invoke($this->Incident,
				"15.3.12.17", -1);
		$this->assertEquals("15", $result);
	}

	public function testGetIdentifyingLocation() {
		$method = new ReflectionMethod('Incident', '_getIdentifyingLocation');
		$method->setAccessible(true);

		$stacktrace = array(
			array(
				'filename' => 'file1',
				'line' => 300,
			),
			array(
				'filename' => 'file2',
				'line' => 200,
			)
		);

		$stacktrace_script = array(
			array(
				'scriptname' => 'script1',
				'line' => 300,
			),
			array(
				'filename' => 'file2',
				'line' => 200,
			)
		);

		$stacktrace_uri = array(
			array(
				'uri' => 'sql.php',
				'line' => 5,
			),
			array(
				'filename' => 'file2',
				'line' => 200,
			)
		);

		$result = $method->invoke($this->Incident,
				$stacktrace_script);
		$this->assertEquals(array('script1', 300), $result);

		$result = $method->invoke($this->Incident,
				$stacktrace);
		$this->assertEquals(array('file1', 300), $result);

		$stacktrace[0]['filename'] = 'tracekit.js';
		$result = $method->invoke($this->Incident,
				$stacktrace);
		$this->assertEquals(array('file2', 200), $result);

		$stacktrace[0]['filename'] = 'error_report.js';
		$result = $method->invoke($this->Incident,
				$stacktrace);
		$this->assertEquals(array('file2', 200), $result);

		$stacktrace[1] = null;
		$result = $method->invoke($this->Incident,
				$stacktrace);
		$this->assertEquals(array('error_report.js', 300), $result);

		$result = $method->invoke($this->Incident,
				$stacktrace_uri);
		$this->assertEquals(array('file2', 200), $result);
	}

	public function testGetSchematizedIncident() {
		$method = new ReflectionMethod('Incident', '_getSchematizedIncident');
		$method->setAccessible(true);

		$bugReport = file_get_contents(TESTS . 'Fixture' . DS . "report_js.json");
		$bugReport = json_decode($bugReport, true);
		$cleanBugReport = Sanitize::clean($bugReport);

		$result = $method->invoke($this->Incident,
				$bugReport);

		$expected = array(
			'pma_version' => '4.0',
			'php_version' => '5.2',
			'steps' => '&lt;script&gt;test steps',
			'error_message' => 'a is not defined',
			'error_name' => 'ReferenceError',
			'browser' => 'FIREFOX 17',
			'user_os' => 'Windows',
			'script_name' => 'tbl_relation.php',
			'configuration_storage' => 'enabled',
			'server_software' => 'NginX/1.17',
			'stackhash' => '9db5408094f1e76ef7161b7bbf3ddfe4',
			'full_report' => json_encode($cleanBugReport),
			'stacktrace' => json_encode($cleanBugReport['exception']['stack']),
			'exception_type' => 0
		);

		$this->assertEquals($expected, $result);
	}

	public function testGetReportDetails() {
		$method = new ReflectionMethod('Incident', '_getReportDetails');
		$method->setAccessible(true);

		$bugReport = file_get_contents(TESTS . 'Fixture' . DS . "report_js.json");
		$bugReport = json_decode($bugReport, true);

		$model = $this->getMockForModel('Incident', array('_getIdentifyingLocation'));
		$model->expects($this->once())
				->method('_getIdentifyingLocation')
				->will($this->returnValue(array('error.js', 312)));

		$result = $method->invoke($model,
				$bugReport);

		$expected = array(
			'error_message' => 'a is not defined',
			'error_name' => 'ReferenceError',
			'status' => 'new',
			'location' => 'error.js',
			'linenumber' => (int) 312,
			'pma_version' => '4.0'
		);

		$this->assertEquals($expected, $result);
	}

	public function testGetClosestReport() {
		$method = new ReflectionMethod('Incident', '_getClosestReport');
		$method->setAccessible(true);

		$bugReport = file_get_contents(TESTS . 'Fixture' . DS . "report_js.json");
		$bugReport = json_decode($bugReport, true);

		$returnedReport = array('Report' => array());

		$incident = $this->getMockForModel('Incident', array('_getIdentifyingLocation'));
		$incident->expects($this->once())
				->method('_getIdentifyingLocation')
				->will($this->returnValue(array('error.js', 312)));

		$report = $this->getMockForModel('Report',
				array('findByLocationAndLinenumberAndPmaVersion'));
		$report->expects($this->once())
				->method('findByLocationAndLinenumberAndPmaVersion')
				->will($this->returnValue($returnedReport));

		$incident->Report = $report;

		$result = $method->invoke($incident,
				$bugReport);

		$this->assertEquals($returnedReport, $result);
	}

	public function testCreateIncidentFromBugReport() {
		$bugReport = file_get_contents(TESTS . 'Fixture' . DS . "report_js.json");
		$bugReport = json_decode($bugReport, true);

		// Case-1: 'js', closest report = null
		$result = $this->Incident->createIncidentFromBugReport($bugReport);
		$this->assertEquals(array(1), $result);

		// [TODO]Case-2: 'js' Incident, closest report = some report.

		// [TODO]Case-3: 'php' Incident, closest report = null.

		// [TODO]Case-4: 'php' Incident, closest report = some report.	
	}
}
