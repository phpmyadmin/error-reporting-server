<?php
/* vim: set expandtab sw=2 ts=2 sts=2: */
/**
 * Application level Controller
 *
 * This file is application-wide controller file. You can put all
 * application-wide controller-related methods here.
 *
 * PHP 5
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * Copyright (c) phpMyAdmin (http://phpmyadmin.net)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright		Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @copyright		Copyright (c) phpMyAdmin (http://phpmyadmin.net)
 * @link				http://cakephp.org CakePHP(tm) Project
 * @package			app.Controller
 * @since				CakePHP(tm) v 0.2.9
 * @license			http://www.opensource.org/licenses/mit-license.php MIT License
 */
App::uses('Controller', 'Controller');
App::uses('Developer', 'Model');
App::uses('Sanitize', 'Utility');
/**
 * Application Controller
 *
 * Add your application-wide methods in the class below, your controllers
 * will inherit them.
 *
 * @package		app.Controller
 * @link		http://book.cakephp.org/2.0/en/controllers.html#the-app-controller
 */
class AppController extends Controller {

	public $components = array(
		'DebugKit.Toolbar',
		'Session',
	);

	public $uses = array('Developer');

	public function beforeFilter() {
		$params = $this->params->params;
		$controller = $params["controller"];
		$action = $params["action"];

		if ($params["controller"] === "reports") {
			$this->set('navigation_class', "active");
		} else {
			$this->set('navigation_class', "");
		}

		if ($this->Session->read('Developer.id')) {
			$current_developer = $this->Developer->
					findById($this->Session->read('Developer.id'));
			$current_developer = Sanitize::clean($current_developer);

			$this->set('current_developer', $current_developer["Developer"]);
			$this->set('developer_signed_in', true);
		} else {
			$this->set('developer_signed_in', false);

			if ($controller !== "pages" && $controller !== "developers" &&
					!($action === "submit" && $controller === "reports")) {
				$this->Session->setFlash("You need to be signed in to do this", "default",
						array("class" => "alert alert-error"));
				$this->redirect("/");
			}
		}
	}
}
