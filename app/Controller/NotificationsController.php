<?php
/* vim: set noexpandtab sw=2 ts=2 sts=2: */
/**
 * Notifications controller handling notification creation and rendering
 *
 * phpMyAdmin Error reporting server
 * Copyright (c) phpMyAdmin project (http://www.phpmyadmin.net)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) phpMyAdmin project (http://www.phpmyadmin.net)
 * @package       Server.Controller
 * @link          http://www.phpmyadmin.net
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
App::uses('AppController', 'Controller');
/**
 * Notifications Controller
 *
 */
class NotificationsController extends AppController {

public $components = array('RequestHandler');

	public $helpers = array('Html', 'Form', 'Reports');

	public $uses = array('Notification', 'Developer', 'Report');

	public function index()
	{
		// no need to do anything here. Just render the view.
	}

	public function data_tables()
	{
		$current_developer = $this->Developer->
					findById($this->Session->read('Developer.id'));
		$current_developer = Sanitize::clean($current_developer);
		$params = array('conditions' => array('developer_id' => $current_developer['Developer']['id']));

		$pagedParams = $params;
		$pagedParams['limit'] = intval($this->request->query('iDisplayLength'));
		$pagedParams['offset'] = intval($this->request->query('iDisplayStart'));

		$rows = $this->Notification->find('all', $pagedParams);
		$rows = Sanitize::clean($rows);

		// Make the display rows array
		$dispRows = array();
		$tmp_row = array();
		foreach($rows as $row) {
			$tmp_row[0] = '<input type="checkbox" name="report_notif_'.$row['Notification']['id'].'"/>';
			$tmp_row[1] ='<a href="'
				. Router::url(
					array(
						'controller' => 'reports',
						'action' => 'view',
						$row['Notification']['report_id']
						)
					)
				. '">'
				. $row['Notification']['report_id']
				. '</a>';
			$tmp_row[2] = $row['Report']['error_name'];
			$tmp_row[3] = $row['Report']['error_message'];
			$tmp_row[4] = $row['Report']['pma_version'];
			$tmp_row[5] = ($row['Report']['exception_type'])?('php'):('js');
			$tmp_row[6] = $row['Notification']['created'];
			array_push($dispRows, $tmp_row);
		}
		$response = array(
			'iTotalDisplayRecords' => count($dispRows),
			'iTotalRecords' => $this->Notification->find('count', $params),
			'sEcho' => intval($this->request->query('sEcho')),
			'aaData' => $dispRows
		);
		$this->autoRender = false;
		return json_encode($response);
	}
}
