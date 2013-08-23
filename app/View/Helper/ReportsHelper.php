<?php
/* vim: set noexpandtab sw=2 ts=2 sts=2: */
App::uses('AppHelper', 'View/Helper');
App::uses('IncidentsHelper', 'View/Helper');
App::uses('Sanitize', 'Utility');
App::uses('Inflector', 'Utility');

class ReportsHelper extends AppHelper {
	public $helpers = array('Incidents');

	public function __construct(View $view, $settings = array()) {
		parent::__construct($view, $settings);
	}

	public function entriesFromIncidents($entries, $totalCount) {
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

	public function createReportsLinks($reports) {
		$links = array();
		foreach ($reports as $report) {
			$links[] = $this->linkToReport($report);
		}
		$string = implode(", ", $links);
		return $string;
	}

	public function linkToReport($report) {
		$reportId = $report["Report"]["id"];
		$link = "<a href='/reports/view/$reportId'>#$reportId</a>";
		return $link;
	}
	
	public function getStacktracesForIncidents($incidents) {
		$count = 0;
		$html = '<div class="row">';
		foreach ($incidents as $incident) {
			$class = "well span5";

			if ($count % 2 == 1) {
				$class .= " ";
			} else {
				$html .= "</div><div class='row'>";
			}

			$html .= $this->Incidents->getStacktrace($incident, $class);
			$count++;
		}
		$html .= '</div>';
		return $html;
	}

	public function getChartArray($arrayName, $columns, $relatedEntries) {
		$html = "var $arrayName = [], chart = {};";
		foreach ($columns as $column) {
			$column = htmlspecialchars($column);
			$html .= "chart = {};";
			$html .= "chart.name = '$column';";
			$html .= "chart.title = '" . Inflector::humanize($column) . "';";
			$html .= "chart.labels = []; chart.values = [];";
			foreach ($relatedEntries[$column] as $entry => $count) {
				$html .= "chart.labels.push('$entry ($count)');";
				$html .= "chart.values.push($count);";
			}
			$html .= "$arrayName.push(chart);";
		}
		return $html;
	}

	protected function _getStackLevelInfo($level) {
		$html = "<span>";
		$elements = array("filename", "scriptname", "line", "func", "column");
		foreach ($elements as $element) {
			if (isset($level[$element])) {
				$html .= "$element: " . $level[$element] . "; ";
			}
		}
		$html .= "</span>";
		return $html;
	}
}
