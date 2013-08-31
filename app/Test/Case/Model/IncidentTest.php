<?php
App::uses('Controller', 'Controller');
App::uses('View', 'View');
App::uses('Incident', 'Model');

class IncidentTest extends CakeTestCase {
	
	public function setUp() {
		parent::setUp();
		$this->Incident = ClassRegistry::init('Incident');
	}

	public function testIsSameStacktrace() {
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

		$stacktrace_different_key = array(
			array(
				'scriptname' => 'script1',
				'line' => 300,
			),
			array(
				'filename' => 'file2',
				'line' => 100,
			)
		);

		$stacktrace_different_value = array(
			array(
				'filename' => 'file2',
				'line' => 300,
			),
			array(
				'filename' => 'file2',
				'line' => 100,
			)
		);

		$stacktrace_different_size = array(
			array(
				'scriptname' => 'script1',
				'line' => 300,
			),
		);

		$method = new ReflectionMethod('Incident', '_isSameStacktrace');
		$method->setAccessible(true);

		$result = $method->invoke($this->Incident, $stacktrace, $stacktrace);
		$this->assertEquals(true, $result);

		$result = $method->invoke($this->Incident, $stacktrace,
				$stacktrace_different_key);
		$this->assertEquals(false, $result);

		$result = $method->invoke($this->Incident, $stacktrace,
				$stacktrace_different_value);
		$this->assertEquals(false, $result);

		$result = $method->invoke($this->Incident, $stacktrace,
				$stacktrace_different_size);
		$this->assertEquals(false, $result);
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

	public function testGetSimplePhpVersion() {
		$method = new ReflectionMethod('Incident', '_getSimplePhpVersion');
		$method->setAccessible(true);

		$result = $method->invoke($this->Incident,
				"5.3.2.17");
		$this->assertEquals("5.3", $result);
	}

	public function testGetMajorVersion() {
		$method = new ReflectionMethod('Incident', '_getMajorVersion');
		$method->setAccessible(true);

		$result = $method->invoke($this->Incident,
				"23.3.2.17");
		$this->assertEquals("23", $result);
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

		$bugReport = file_get_contents(TESTS . 'Fixture' . DS . "report.json");
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
			'full_report' => json_encode($cleanBugReport),
			'stacktrace' => json_encode($cleanBugReport['exception']['stack']),
		);

		$this->assertEquals($expected, $result);
	}

	public function testGetReportDetails() {
		$method = new ReflectionMethod('Incident', '_getReportDetails');
		$method->setAccessible(true);

		$bugReport = file_get_contents(TESTS . 'Fixture' . DS . "report.json");
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

		$bugReport = file_get_contents(TESTS . 'Fixture' . DS . "report.json");
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

	public function testHasDifferentStacktrace() {
		$method = new ReflectionMethod('Incident', '_hasDifferentStacktrace');
		$method->setAccessible(true);

		$stacktrace = json_encode(array(
			array(
				'filename' => 'file1',
				'line' => 300,
			),
			array(
				'filename' => 'file2',
				'line' => 200,
			)
		));

		$stacktrace_different = json_encode(array(
			array(
				'scriptname' => 'script1',
				'line' => 300,
			),
			array(
				'filename' => 'file2',
				'line' => 200,
			)
		));

		$result = $method->invoke($this->Incident,
				array('stacktrace' => $stacktrace),
				array(array('Incident' => array('stacktrace' => $stacktrace))));

		$this->assertEquals(false, $result);

		$result = $method->invoke($this->Incident,
				array('stacktrace' => $stacktrace_different),
				array(array('Incident' => array('stacktrace' => $stacktrace))));

		$this->assertEquals(true, $result);

		$result = $method->invoke($this->Incident,
				array('stacktrace' => $stacktrace_different),
				array());

		$this->assertEquals(true, $result);
	}

	public function testCreateIncidentFromBugReport() {
		$bugReport = file_get_contents(TESTS . 'Fixture' . DS . "report.json");
		$bugReport = json_decode($bugReport, true);

		$closestReport = array('Report' => array('id' => 2));

		$incident = $this->getMockForModel('Incident',
				array('_getClosestReport', 'save', 'saveAssociated',
				'_getSchematizedIncident'));
		$incident->expects($this->any())
        ->method('_getClosestReport')
        ->will($this->onConsecutiveCalls($closestReport, null));
		$incident->expects($this->any())
        ->method('_getSchematizedIncident')
        ->will($this->returnValue(array('stacktrace' => '')));
		$incident->expects($this->once())
        ->method('save')
				->with($this->equalTo(array('different_stacktrace' => true,
						'report_id' => 2, 'stacktrace' => '')))
        ->will($this->returnValue(true));


		$report = $this->getMockForModel('Report',
				array('getIncidentsWithDifferentStacktrace', 'read'));

		$report->expects($this->once())
        ->method('getIncidentsWithDifferentStacktrace')
        ->will($this->returnValue(array()));

		$result = $incident->createIncidentFromBugReport($bugReport);

		$this->assertEquals(true, $result);

		$incident->expects($this->once())
        ->method('_getClosestReport')
        ->will($this->returnValue(null));

		$incident->expects($this->once())
        ->method('saveAssociated')
        ->will($this->returnValue(true));

		$result = $incident->createIncidentFromBugReport($bugReport);

		$this->assertEquals(true, $result);
	}
}
