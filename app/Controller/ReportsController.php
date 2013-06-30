<?php
class ReportsController extends AppController {
    public $helpers = array('Html', 'Form');

    public function index() {
        $this->set('reports', $this->Report->find('all'));
    }

    public function submit() {
    }
}

