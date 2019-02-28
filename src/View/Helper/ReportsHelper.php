<?php

/* vim: set expandtab sw=4 ts=4 sts=4: */

namespace app\View\Helper;

use App\Utility\Sanitize;
use App\View\Helper\AppHelper;
use Cake\Utility\Inflector;
use Cake\View\View;

class ReportsHelper extends AppHelper
{
    public $helpers = array('Incidents');

    public function __construct(View $view, $settings = array())
    {
        parent::__construct($view, $settings);
    }

    public function entriesFromIncidents($entries, $totalCount, $key)
    {
        //$entries = Sanitize::clean($entries);
        $values = array();
        foreach ($entries as $entry) {
            $values[] = "$entry[$key] <span class='count'>("
                . $entry['count'] . ')</span>';
        }
        $fullString = implode(', ', $values);
        $remaining = $totalCount - count($values);
        if ($remaining) {
            $fullString .= " <small>and $remaining others</small>";
        }

        return $fullString;
    }

    public function createReportsLinks($reports)
    {
        $links = array();
        foreach ($reports as $report) {
            $links[] = $this->linkToReport($report);
        }
        $string = implode(', ', $links);

        return $string;
    }

    public function linkToReport($report)
    {
        $reportId = $report['id'];
        $link = '<a href=/' . BASE_DIR . "reports/view/$reportId>#$reportId</a>";

        return $link;
    }

    public function linkToReportFromIncident($incident)
    {
        $reportId = $incident['report_id'];
        $link = '<a href=/' . BASE_DIR . "reports/view/$reportId>#$reportId</a>";

        return $link;
    }

    public function getStacktracesForIncidents($incidents)
    {
        $count = 0;
        $html = '<div class="row">';
        foreach ($incidents as $incident) {
            $class = 'well span5';

            if (1 == $count % 2) {
                $class .= ' ';
            } else {
                $html .= "</div><div class='row'>";
            }

            $html .= $this->Incidents->getStacktrace($incident, $class);
            ++$count;
        }
        $html .= '</div>';

        return $html;
    }

    public function getChartArray($arrayName, $columns, $relatedEntries)
    {
        $html = "var $arrayName = [], chart = {};";
        foreach ($columns as $column) {
            $column = htmlspecialchars($column, ENT_QUOTES | ENT_HTML5);
            $html .= 'chart = {};';
            $html .= "chart.name = '$column';";
            $html .= "chart.title = '" . Inflector::humanize($column) . "';";
            $html .= 'chart.labels = []; chart.values = [];';
            foreach ($relatedEntries[$column] as $entry) {
                $count = $entry['count'];
                $html .= "chart.labels.push('$entry[$column] ($count)');";
                $html .= "chart.values.push($count);";
            }
            $html .= "$arrayName.push(chart);";
        }

        return $html;
    }

    public function getLineChartData($arrayName, $entries)
    {
        $html = "var $arrayName = [];";
        foreach ($entries as $entry) {
            $html .= "$arrayName.push(['" . $entry['date'] . "', "
                    . $entry['count'] . ']);';
        }

        return $html;
    }
}
