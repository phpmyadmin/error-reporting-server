<?php
/* vim: set noexpandtab sw=2 ts=2 sts=2: */
App::uses('AppHelper', 'View/Helper');
App::uses('Sanitize', 'Utility');

class IncidentsHelper extends AppHelper {

	public function __construct(View $view, $settings = array()) {
		parent::__construct($view, $settings);
	}

	public function createIncidentsLinks($incidents) {
		$links = array();
		foreach ($incidents as $incident) {
			$links[] = $this->linkToIncident($incident);
		}
		$string = implode(", ", $links);
		return $string;
	}

	public function linkToIncident($incident) {
		$incidentId = $incident["Incident"]["id"];
		$link = "<a href='/incidents/view/$incidentId'>#$incidentId</a>";
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

	public function getStacktrace($incident, $divClass) {
		$html = "";
		$html .= "<div class='$divClass'>";

		if (is_string($incident["Incident"]["stacktrace"])) {
			$incident["Incident"]["stacktrace"] =
					json_decode($incident["Incident"]["stacktrace"], true);
		}

		foreach ($incident["Incident"]["stacktrace"] as $level) {
			$html .= $this->_getStackLevelInfo($level);
			$html .= "<pre class='brush: js; tab-size: 2'>";
			$html .= join("\n", $level["context"]);
			$html .= "</pre>";
		}
		$html .= "</div>";
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
