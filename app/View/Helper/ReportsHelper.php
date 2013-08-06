<?php
/* vim: set expandtab sw=2 ts=2 sts=2: */
App::uses('AppHelper', 'View/Helper');
App::uses('Sanitize', 'Utility');

class ReportsHelper extends AppHelper {

	public function __construct(View $view, $settings = array()) {
		parent::__construct($view, $settings);
	}

	public function entriesFromRelateReports($entries, $totalCount) {
		$entries = Sanitize::clean($entries);
		$values = array();
		foreach($entries as $entry => $count) {
			$values[] = "$entry <span class='count'>($count)</span>";
		}
		$fullString = implode(", ", $values);
		$remaining = $totalCount - count($values);
		if ($remaining) {
			$fullString .= " <small>and $remaining others</small>";
		}
		return $fullString;
	}

	public function createIncidentsLinks($incidents) {
		$links = array();
		foreach ($incidents as $incident) {
			$links[] = $this->linkToIncident($incident);
		}
		$string = implode(", ", $links);
		return $string;
	}

	public function createReportsLinks($reports) {
		$links = array();
		foreach ($reports as $report) {
			$links[] = $this->linkToReport($report);
		}
		$string = implode(", ", $links);
		return $string;
	}

	public function linkToIncident($incident) {
		$incidentId = $incident["Incident"]["id"];
		$link = "<a href='/incidents/json/$incidentId'>#$incidentId</a>";
		return $link;
	}

	public function linkToReport($report) {
		$reportId = $report["Report"]["id"];
		$link = "<a href='/reports/view/$reportId'>#$reportId</a>";
		return $link;
	}

	public function incidentsDescriptions($incidents) {
		$descriptions = "";
		foreach ($incidents as $incident) {
			$descriptions .= "<span>Incident ";
			$descriptions .= $this->linkToIncident($incident);
			$descriptions .= ":</span>";
			$descriptions .= "<pre>";
			$descriptions .= $incident["Incident"]["steps"];
			$descriptions .= "</pre>";
		}
		return $descriptions;
	}
}
