<?php
App::uses('AppHelper', 'View/Helper');
App::uses('Sanitize', 'Utility');

class ReportsHelper extends AppHelper {
  public function __construct(View $view, $settings = array()) {
    parent::__construct($view, $settings);
  }

  public function entries_from_related_reports($entries, $total_count) {
    $entries = Sanitize::clean($entries);
    $values = array();
    foreach($entries as $entry => $count) {
      $values[] = "$entry <span class='count'>($count)</span>";
    }
    $full_string = implode(", ", $values);
    $remaining = $total_count - count($values);
    if ($remaining) {
      $full_string .= " <small>and $remaining others</small>";
    }
    return $full_string;
  }

  public function create_reports_links($reports) {
    $links = array();
    foreach ($reports as $report) {
      $report_id = $report["Report"]["id"];
      $links[] = "<a href='/reports/view/$report_id'>#$report_id</a>";
    }
    $string = implode(", ", $links);
    return $string;
  }
}
