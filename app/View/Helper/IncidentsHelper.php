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
		$link = "<a href='/" . BASE_DIR . "incidents/view/$incidentId'>#$incidentId</a>";
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
			$exception_type = (($incident["Incident"]["exception_type"])?('php'):('js'));
			$html .= $this->_getStackLevelInfo($level, $exception_type);
			$html .= "<pre class='brush: "
				. $exception_type
				. "; tab-size: 2'>";
			if($exception_type == 'js') {
				$html .= join("\n", $level["context"]);
			} else {
				$html .= $level["function"];
				$html .= "(";
				$argList = "";
				if (count($level["args"]) > 0) {
					foreach ($level["args"] as $arg) {
						$argList .= "\n"
							. getType($arg)
							. " => "
							. $arg;
						$argList .= ",";
					}
					$argList = substr($argList, 0, (strlen($argList)-1));
					$argList .= "\n";
				}
				$html .= $argList;
				$html .= ")";
			}
			$html .= "</pre>";
		}
		$html .= "</div>";
		return $html;
	}

	protected function _getStackLevelInfo($level, $exception_type = 'js') {
		$html = "<span>";
		if ($exception_type == 'js') {
			$elements = array("filename", "scriptname", "line", "func", "column");
		} else {
			$elements = array("file", "line", "function", "class");
		}
		foreach ($elements as $element) {
			if (isset($level[$element])) {
				$html .= "$element: " . $level[$element] . "; ";
			}
		}
		$html .= "</span>";
		return $html;
	}
}
