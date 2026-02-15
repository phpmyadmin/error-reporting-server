<?php

namespace App\View\Helper;

use Cake\Utility\Inflector;

use function count;
use function implode;
use function json_encode;

use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

class ReportsHelper extends AppHelper
{
    /** @var string */
    public array $helpers = ['Incidents'];

    /**
     * @param array[] $entries    Entries
     * @param int     $totalCount Total count
     * @param string  $key        Key
     * @return string HTML
     */
    public function entriesFromIncidents(array $entries, int $totalCount, string $key): string
    {
        //$entries = Sanitize::clean($entries);
        $values = [];
        foreach ($entries as $entry) {
            $values[] = $entry[$key] . ' <span class="count">('
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
     * @return string HTML code
     */
    public function getChartArray(string $arrayName, array $columns, array $relatedEntries): string
    {
        $finalData = [];
        foreach ($columns as $column) {
            $data = [
                'name' => $column,
                'title' => Inflector::humanize($column),
                'labels' => [],
                'values' => [],
            ];
            foreach ($relatedEntries[$column] as $entry) {
                $count = $entry['count'];
                $data['labels'][] = $entry[$column] . ' (' . $count . ')';
                $data['values'][] = $count;
            }

            $finalData[] = $data;
        }

        return 'var ' . $arrayName . ' = '
            . json_encode(
                $finalData,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            ) . ';';
    }

    /**
     * @return string HTML
     */
    public function getLineChartData(string $arrayName, array $entries): string
    {
        $data = [];
        foreach ($entries as $entry) {
            $data[] = [$entry['date'], $entry['count']];
        }

        return 'var ' . $arrayName . ' = '
        . json_encode(
            $data,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ) . ';';
    }
}
