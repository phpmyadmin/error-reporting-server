<?php
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
			$incidentId = $incident["Incident"]["id"];
			$links[] = "<a href='/incidents/view/$incidentId'>#$incidentId</a>";
		}
		$string = implode(", ", $links);
		return $string;
	}
}
