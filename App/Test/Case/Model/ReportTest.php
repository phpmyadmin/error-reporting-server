<?php
namespace app\Test\Case\Model;

use App\Model\Report;
use Cake\Controller\Controller;
use Cake\TestSuite\TestCase;
use Cake\View\View;

class ReportTest extends TestCase {

	public $fixtures = array(
		'app.reports',
		'app.incidents'
	);

	public function setUp() {
		parent::setUp();
		$this->Report = ClassRegistry::init('Report');
	}

	public function testGetIncidents() {
		$this->Report->read(null, 4);
		$incidents = $this->Report->getIncidents();
		$this->assertEquals(count($incidents), 3);

		$this->Report->saveField("related_to", null);
		$incidents = $this->Report->getIncidents();
		$this->assertEquals(count($incidents), 2);
	}

	public function testGetRelatedReports() {
		$this->Report->read(null, 2);
		$reports = $this->Report->getRelatedReports();
		$this->assertEquals(count($reports), 0);

		$this->Report->read(null, 4);
		$reports = $this->Report->getRelatedReports();
		$this->assertEquals(count($reports), 1);
	}

	public function testGetIncidentsWithDescription() {
		$this->Report->read(null, 4);
		$incidents = $this->Report->getIncidentsWithDescription();
		$this->assertEquals(count($incidents), 2);
	}

	public function testGetIncidentsWithDifferentStacktrace() {
		$this->Report->read(null, 4);
		$incidents = $this->Report->getIncidentsWithDifferentStacktrace();
		$this->assertEquals(count($incidents), 2);
	}

	public function testRemoveFromRelatedGroup() {
		$this->Report->read(null, 1);
		$this->Report->removeFromRelatedGroup();
		$incidents = $this->Report->getIncidents();
		$this->assertEquals(count($incidents), 1);
	}

	public function testGetUrl() {
		$this->Report->read(null, 1);
		$this->assertStringEndsWith("/reports/view/1",
				$this->Report->getUrl());
	}

	public function testAddToRelatedGroup() {
		$this->Report->read(null, 2);
		$this->Report->addToRelatedGroup(4);

		$this->Report->read(null, 2);
		$incidents = $this->Report->getIncidents();
		$this->assertEquals(count($incidents), 3);

		$this->Report->saveField("related_to", null);
		$this->Report->addToRelatedGroup(1);
		$this->Report->read(null, 2);
		$incidents = $this->Report->getIncidents();
		$this->assertEquals(count($incidents), 3);
	}

	public function testGetRelatedByField() {
		$this->Report->read(null, 1);
		$result = $this->Report->getRelatedByField('php_version');
		$expected = array(
			'5.3' => '2',
			'5.5' => '1'
		);
		$this->assertEquals($expected, $result);

		$result = $this->Report->getRelatedByField('php_version', 1);
		$expected = array(
			'5.3' => '2',
		);
		$this->assertEquals($expected, $result);

		$result = $this->Report->getRelatedByField('php_version', 10, false, true,
				'2013-08-29 18:10:01');
		$expected = array(
			'5.3' => '1',
			'5.5' => '1'
		);
		$this->assertEquals($expected, $result);

		$result = $this->Report->getRelatedByField('php_version', 10, false, false);
		$expected = array(
			'5.3' => '3',
			'5.5' => '1'
		);
		$this->assertEquals($expected, $result);

		$result = $this->Report->getRelatedByField('php_version', 10, true);
		$expected = array(
			array(
				'5.3' => '2',
				'5.5' => '1'
			), 2);
		$this->assertEquals($expected, $result);
	}

	public function testFindAllDataTable() {
		$params = array( 'fields' => 'Report.id');
		$result = $this->Report->find('allDataTable', $params);
		$expected = array(
			array('1'),
			array('2'),
			array('4'),
		);
		$this->assertEquals($expected, $result);
	}

	public function testFindArrayList() {
		$this->Report->recursive = -1;
		$result = $this->Report->find('arrayList', array(
			'fields' => array('DISTINCT Report.pma_version'),
		));
		$expected = array('4.0', '3.8');
		$this->assertEquals($expected, $result);
	}
}
