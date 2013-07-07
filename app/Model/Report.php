<?php
App::uses('AppModel', 'Model');
class Report extends AppModel {
  public $validate = array(
    'error_message' => array(
      'rule' => 'notEmpty',
      'required'   => true,
    ),
    'pma_version' => array(
      'rule' => 'notEmpty',
      'required'   => true,
    ),
    'full_report' => array(
      'rule' => 'notEmpty',
      'required'   => true,
    ),
    'stacktrace' => array(
      'rule' => 'notEmpty',
      'required'   => true,
    ),
  );

  public $findMethods = array('allDataTable' =>  true, 'arrayList' => true);

  public function save($raw_report = array(), $validate = true,
      $fieldList = array()) {
    $schematized_report = array(
      'pma_version' => $raw_report['pma_version'],
      'steps' => $raw_report['description'],
      'error_message' => $raw_report['exception']['message'],
      'error_name' => $raw_report['exception']['name'],
      'browser_name' => $raw_report['browser_agent'],
      'browser_version' => $raw_report['browser_version'],
      'user_os' => $raw_report['user_os'],
      'server_software' => $raw_report['server_software'],
      'full_report' => json_encode($raw_report),
      'stacktrace' => json_encode($raw_report['exception']['stack']),
    );
    return parent::save($schematized_report, $validate, $fieldList);
  }

  protected function _findAllDataTable($state, $query, $results = array()) {
    if ($state === 'before') {
      return $query;
    }
    $output = array();
    foreach ($results as $row) {
      $output_row = array();
      foreach ($row['Report'] as $key => $value) {
        $output_row[] = $value;
      }
      $output[] = $output_row;
    }
    return $output;
  }

  protected function _findArrayList($state, $query, $results = array()) {
    if ($state === 'before') {
      return $query;
    }
    $output = array();
    foreach ($results as $row) {
      foreach ($row['Report'] as $key => $value) {
        $output[] = $value;
      }
    }
    return $output;
  }
}
