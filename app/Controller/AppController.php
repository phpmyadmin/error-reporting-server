<?php
/* vim: set expandtab sw=2 ts=2 sts=2: */
/**
 * Application level Controller
 *
 * This file is application-wide controller file. You can put all
 * application-wide controller-related methods here.
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

App::uses('Controller', 'Controller');
App::uses('Developer', 'Model');
App::uses('Sanitize', 'Utility');
App::uses('Inflector', 'Utility');
App::uses('Notification', 'Model');

/**
 * Application Controller
 *
 * Add your application-wide methods in the class below, your controllers
 * will inherit them.
 *
 * @package		Server.Controller
 * @link		http://book.cakephp.org/2.0/en/controllers.html#the-app-controller
 */
class AppController extends Controller {

	public $components = array(
		'DebugKit.Toolbar',
		'Session',
	);

	public $uses = array('Developer', 'Notification');

	public $whitelist = array(
		'developers',
		'pages',
		'incidents' => array(
			'create',
		),
	);

	public function beforeFilter() {
		$params = $this->params->params;
		$controller = $params["controller"];
        $this->set('current_controller', $controller);
		$notif_count = 0;

		if ($this->Session->read('Developer.id')) {
			$current_developer = $this->Developer->
					findById($this->Session->read('Developer.id'));
			$current_developer = Sanitize::clean($current_developer);
			$notif_count = $this->Notification->find(
				'count',
				array(
					'conditions' => array('developer_id' => intval($current_developer["Developer"]['id']))
				)
			);

			$this->set('current_developer', $current_developer["Developer"]);
			$this->set('developer_signed_in', true);
		} else {
			$this->set('developer_signed_in', false);
			$this->_checkAccess();
		}
		$this->set('notif_count', $notif_count);
	}

	protected function _checkAccess() {
		$params = $this->params->params;
		$controller = $params["controller"];
		$action = $params["action"];

		if (in_array($controller, $this->whitelist)) {
			return;
		}
		if (isset($this->whitelist[$controller]) &&
				in_array($action, $this->whitelist[$controller])) {
			return;
		}

		$this->Session->setFlash("You need to be signed in to do this", "default",
				array("class" => "alert alert-error"));
		$this->Session->write("last_page", $this->here);
		return $this->redirect($this->referer());
	}
}
