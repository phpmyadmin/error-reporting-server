<?php
class ReportsController extends AppController {
  public $helpers = array('Html', 'Form');

  public function index() {
    $this->set('reports', $this->Report->find('all'));
  }

  public function submit() {
    $report = $this->request->input('json_decode', true);
    $this->Report->create(array('status' => 'new'));
    $this->Report->save($report);
    CakeLog::write("debug", print_r($report, true));
    CakeLog::write("debug", print_r(count($this->Report->validationErrors), true));
    $this->autoRender = false;
    $response = array(
      "success" => true,
      "message" => "Thank for your submission",
      "report_id" => $this->Report->id,
    );
    return json_encode($response);
  }
}

