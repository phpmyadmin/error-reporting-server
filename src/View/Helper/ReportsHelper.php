<?php

namespace App\View\Helper;

use Cake\Utility\Inflector;
use const ENT_HTML5;
use const ENT_QUOTES;
use function count;
use function htmlspecialchars;
use function implode;

class ReportsHelper extends AppHelper
{
    /** @var string */
    public $helpers = ['Incidents'];

    /**
     * @param mixed  $entries    Entries
     * @param int    $totalCount Total count
     * @param string $key        Key
     * @return string HTML
     */
    public function entriesFromIncidents($entries, int $totalCount, string $key): string
    {
        //$entries = Sanitize::clean($entries);
        $values = [];
        foreach ($entries as $entry) {
            $values[] = $entry . '[' . $key . '] <span class="count">('
                . $entry['count'] . ')</span>';
        }
        $fullString = implode(', ', $values);
        $remaining = $totalCount - count($values);
        if ($remaining) {
            $fullString .= ' <small>and ' . $remaining . ' others</small>';
        }

        return $fullString;
    }

    /**
     * @param mixed $reports The reports
     * @return string comma separated list
     */
    public function createReportsLinks($reports): string
    {
        $links = [];
        foreach ($reports as $report) {
            $links[] = $this->linkToReport($report);
        }

        return implode(', ', $links);
    }

    /**
     * @param mixed $report The report
     * @return string HTML <a> link
     */
    public function linkToReport($report): string
    {
        $reportId = $report['id'];

        return '<a href="/' . BASE_DIR . 'reports/view/' . $reportId . '">#' . $reportId . '</a>';
    }

    /**
     * @param mixed $incident The incident
     * @return string HTML <a> link
     */
    public function linkToReportFromIncident($incident): string
    {
        $reportId = $incident['report_id'];

        return '<a href="/' . BASE_DIR . 'reports/view/' . $reportId . '">#' . $reportId . '</a>';
    }

    /**
     * @param mixed $incidents The incidents
     * @return string HTML
     */
    public function getStacktracesForIncidents($incidents): string
    {
        $count = 0;
        $html = '<div class="row">';
        foreach ($incidents as $incident) {
            $class = 'well span5';

            if ($count % 2 === 1) {
                $class .= ' ';
            } else {
                $html .= '</div><div class="row">';
            }

            $html .= $this->Incidents->getStacktrace($incident, $class);
            ++$count;
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * @param mixed $columns
     * @param mixed $relatedEntries
     * @return string HTML code
     */
    public function getChartArray(string $arrayName, $columns, $relatedEntries): string
    {
        $html = 'var ' . $arrayName . ' = [], chart = {};';
        foreach ($columns as $column) {
            $column = htmlspecialchars($column, ENT_QUOTES | ENT_HTML5);
            $html .= 'chart = {};';
            $html .= 'chart.name = "' . $column . '";';
            $html .= 'chart.title = "' . Inflector::humanize($column) . '";';
            $html .= 'chart.labels = []; chart.values = [];';
            foreach ($relatedEntries[$column] as $entry) {
                $count = $entry['count'];
                $html .= 'chart.labels.push("' . $entry[$column] . ' (' . $count . ')");';
                $html .= 'chart.values.push(' . $count . ');';
            }
            $html .= $arrayName . '.push(chart);';
        }

        return $html;
    }

    /**
     * @param mixed $entries
     * @return string HTML
     */
    public function getLineChartData(string $arrayName, $entries): string
    {
        $html = 'var $arrayName = [];';
        foreach ($entries as $entry) {
            $html .= $arrayName . '.push(["' . $entry['date'] . '", '
                    . $entry['count'] . ']);';
        }

        return $html;
    }
}
