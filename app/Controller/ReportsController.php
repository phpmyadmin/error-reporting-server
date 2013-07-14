<?php
class ReportsController extends AppController {
  public $helpers = array('Html', 'Form');

  public function index() {
    $this->set('distinct_statuses',
      $this->Report->find('arrayList', array(
        'fields' => array('DISTINCT Report.status'), 
      ))
    );
    $this->set('distinct_versions',
      $this->Report->find('arrayList', array(
        'fields' => array('DISTINCT Report.pma_version'), 
      ))
    );
  }

  public function view($id) {
    if (!$id) {
      throw new NotFoundException(__('Invalid Report'));
    }

    $report = $this->Report->findById($id);
    if (!$report) {
      throw new NotFoundException(__('Invalid Report'));
    }

    $this->set('report', $report);
  }

  public function submit() {
    $report = $this->request->input('json_decode', true);
    $this->Report->create(array('status' => 'new'));
    $this->Report->save($report);
    $response = array(
      "success" => true,
      "message" => "Thank you for your submission",
      "report_id" => $this->Report->id,
    );
    $this->autoRender = false;
    return json_encode($response);
  }

  public function ajax() {
    CakeLog::write("debug", print_r($this->request["url"], true));
    $aColumns = array('id', 'error_name', 'error_message', 'pma_version',
          'status');
    $search_conditions = $this->getSearchConditions($aColumns);
    $order_conditions = $this->getOrder($aColumns);

    $params = array(
      'fields' => $aColumns,
      'conditions' => $search_conditions,
      'order' => $order_conditions,
    );

    $paged_params = $params;
    $paged_params['limit'] = intval($this->request->query('iDisplayLength'));
    $paged_params['offset'] = intval($this->request->query('iDisplayStart'));

    $rows = $this->Report->find('allDataTable', $paged_params);
    $rows = Sanitize::clean($rows);
    $total_filtered = $this->Report->find('count', $params);

    $response = array(
      'iTotalRecords' => $this->Report->find('count'),
      'iTotalDisplayRecords' => $total_filtered,
      'sEcho' => intval($this->request->query('sEcho')),
      'aaData' => $rows
    );
    $this->autoRender = false;
    return json_encode($response);
  }

  private function getSearchConditions($aColumns) {
    $search_conditions = array('OR' => array());
    if ( $this->request->query('sSearch') != "" )
    {
      for ( $i=0 ; $i<count($aColumns) ; $i++ )
      {
        if ($this->request->query('bSearchable_'.$i) == "true") {
          $search_conditions['OR'][] = array($aColumns[$i]." LIKE" => "%".
              $this->request->query('sSearch')."%");
        }
      }
    }
    
    /* Individual column filtering */
    for ( $i=0 ; $i<count($aColumns) ; $i++ )
    {
      if ($this->request->query('sSearch_'.$i) != '')
      {
        $search_conditions[] = array($aColumns[$i]." LIKE" =>
            "%".$this->request->query('sSearch_'.$i)."%");
      }
    }
    return $search_conditions;
  }

  private function getOrder($aColumns) {
    if ( $this->request->query('iSortCol_0') != null )
    {
      $order = array();
      for ( $i=0 ; $i<intval($this->request->query('iSortingCols')) ; $i++ )
      {
        if ( $this->request->query('bSortable_'
            .intval($this->request->query('iSortCol_'.$i))) == "true" )
        {
          $order[] = array(
            $aColumns[intval($this->request->query('iSortCol_'.$i))] =>
              $this->request->query('sSortDir_'.$i)
          );
        }
      }
      return $order;
    } else {
      return null;
    }
  }
}

