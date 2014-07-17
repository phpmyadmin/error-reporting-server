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

	public function beforeFilter() {
		if ($this->action != 'clean_old_notifs') {
			parent::beforeFilter();
		}
	}

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
			$tmp_row[0] = '<input type="checkbox" name="notifs[]" value="'
				. $row['Notification']['id']
				. '"/>';
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

	/**
	 * To carry out mass actions on Notifications.
	 * Currently it deletes them (marks them "read").
	 * Can be Extended for other mass operations as well.
	 * Expects an array of Notification Ids as a POST parameter.
	 *
	 */
	public function mass_action()
	{
		$msg = "Selected Notifications have been marked <i>Read</i>!";
		$flash_class = "alert alert-success";
		foreach($this->request->data['notifs'] as $notif_id)
		{
			if(!$this->Notification->delete(intval($notif_id), false)) {
				$msg = "<b>ERROR</b>: There was some problem in deleting Notification(ID:"
					. $notif_id
					. ")!";
				$flash_class = "alert alert-error";
				break;
			}
		}
		$this->Session->setFlash($msg, "default", array("class" => $flash_class));
		$this->redirect("/notifications/");
	}

	/**
	 * Cron Action to clean older Notifications.
	 * Can not (& should not) be directly accessed via Web.
	 */
	public function clean_old_notifs()
	{
		if (!defined('CRON_DISPATCHER')) {
			$this->redirect('/');
			exit();
		}
		// X Time: All the notifications before this time are to be deleted.
		// Currently it's set to 60 days from current time.
		$XTime = time() - 60*24*3600;
		$conditions = array('Notification.created <' => date('Y-m-d H:i:s', $XTime));
		if (!$this->Notification->deleteAll($conditions)) {
			CakeLog::write(
				'cron_jobs',
				'FAILED: Deleting older Notifications!!',
				'cron_jobs'
			);
		}
		$this->autoRender = false;
	}
}
