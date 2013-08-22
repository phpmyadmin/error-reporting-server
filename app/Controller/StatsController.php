<?php
/* vim: set noexpandtab sw=2 ts=2 sts=2: */

App::uses('Sanitize', 'Utility');
App::uses('AppController', 'Controller');

class StatsController extends AppController {
  
  public $uses = array("Report", "Incident");

  public $helper = array("Reports");

  public function stats() {
  }
}
