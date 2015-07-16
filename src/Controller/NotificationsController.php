<?php
/* vim: set noexpandtab sw=2 ts=2 sts=2: */
namespace App\Controller;

use App\Controller\AppController;
use Cake\Log\Log;
use Cake\Routing\Router;
use Cake\Event\Event;
use Cake\ORM\TableRegistry;

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
/**
 * Notifications Controller
 *
 */
class NotificationsController extends AppController {

public $components = array('RequestHandler');

	public $helpers = array('Html', 'Form', 'Reports');

	public $uses = array('Notification', 'Developer', 'Report');

	public function beforeFilter(Event $event) {
		if ($this->action != 'clean_old_notifs') {
			parent::beforeFilter($event);
		}
	}

	public function index()
	{
		// no need to do anything here. Just render the view.
	}

	public function data_tables()
	{
		$current_developer = TableRegistry::get('Developers')->
					findById($this->request->session()->read('Developer.id'))->all()->first();
		//$current_developer = Sanitize::clean($current_developer
		$params = ['conditions' => ['Notifications.developer_id = ' . $current_developer['id']]];

		$pagedParams = $params;
		$pagedParams['limit'] = intval($this->request->query('iDisplayLength'));
		$pagedParams['offset'] = intval($this->request->query('iDisplayStart'));

		$rows = $this->Notifications->find('all', $pagedParams)->contain(['Reports']);
		//$rows = Sanitize::clean($rows);

		// Make the display rows array
		$dispRows = array();
		$tmp_row = array();
		foreach($rows as $row) {
			$tmp_row[0] = '<input type="checkbox" name="notifs[]" value="'
				. $row['id']
				. '"/>';
			$tmp_row[1] ='<a href="'
				. Router::url(
					array(
						'controller' => 'reports',
						'action' => 'view',
						$row['report_id']
						)
					)
				. '">'
				. $row['report_id']
				. '</a>';
			$tmp_row[2] = $row['report']['error_name'];
			$tmp_row[3] = $row['report']['error_message'];
			$tmp_row[4] = $row['report']['pma_version'];
			$tmp_row[5] = ($row['report']['exception_type'])?('php'):('js');
			$tmp_row[6] = $row['created'];
			array_push($dispRows, $tmp_row);
		}
		$response = array(
			'iTotalDisplayRecords' => count($dispRows),
			'iTotalRecords' => $this->Notifications->find('all', $params)->count(),
			'sEcho' => intval($this->request->query('sEcho')),
			'aaData' => $dispRows
		);
		$this->autoRender = false;
        $this->response->body(json_encode($response));
        return $this->response;
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
			if(!$this->Notifications->delete($this->Notifications->get(intval($notif_id)))) {
				$msg = "<b>ERROR</b>: There was some problem in deleting Notification(ID:"
					. $notif_id
					. ")!";
				$flash_class = "alert alert-error";
				break;
			}
		}
		$this->Flash->default($msg, array("class" => $flash_class));
		$this->redirect("/notifications/");
	}
}
