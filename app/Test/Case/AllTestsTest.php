<?php
class AllTestsTest extends CakeTestSuite {
	protected $coverageSetup = false;

	public static function suite() {
		$suite = new self();
		$suite->addTestDirectoryRecursive(TESTS . 'Case');
		return $suite;
	}

	public function run(PHPUnit_Framework_TestResult $result = NULL, $filter = FALSE,
			array $groups = array(), array $excludeGroups = array(), $processIsolation = FALSE) {
		if ($result === NULL) {
			$result = $this->createResult();
		}
		if (!$this->coverageSetup) {
			$coverage = $result->getCodeCoverage();
			if ($coverage) { // If the CodeCoverage is not installed or disabled
				$coverage->setProcessUncoveredFilesFromWhitelist(true);

				$coverageFilter = $coverage->filter();
				$coverageFilter->addDirectoryToBlacklist(CAKE);
				$coverageFilter->addDirectoryToBlacklist(APP . DS . 'Test');
				$coverageFilter->addDirectoryToBlacklist(APP . DS . 'Plugin');
				$coverageFilter->addDirectoryToBlacklist(APP . DS . 'Config' . DS .
						'Migration');
			}
			$this->coverageSetup = true;
		}
		return parent::run($result, $filter, $groups, $excludeGroups, $processIsolation);
	}
}
