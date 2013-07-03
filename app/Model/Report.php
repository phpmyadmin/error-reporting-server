<?php
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
  public function save($raw_report = array(), $validate = true,
      $fieldList = array()) {
    $schematized_report = array();
    $schematized_report['pma_version'] = $raw_report['pma_version'];
    $schematized_report['steps'] = $raw_report['description'];
    $schematized_report['error_message'] = $raw_report['exception']['message'];
    $schematized_report['error_name'] = $raw_report['exception']['name'];
    $schematized_report['browser_name'] = $raw_report['browser_agent'];
    $schematized_report['browser_version'] = $raw_report['browser_version'];
    $schematized_report['user_os'] = $raw_report['user_os'];
    $schematized_report['server_software'] = $raw_report['server_software'];
    $schematized_report['stacktrace'] =
        json_encode($raw_report['exception']['stack']);
    $schematized_report['full_report'] = json_encode($raw_report);

    return parent::save($schematized_report, $validate, $fieldList);
  }
}
