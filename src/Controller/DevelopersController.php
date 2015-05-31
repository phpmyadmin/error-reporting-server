<?php
/* vim: set noexpandtab sw=2 ts=2 sts=2: */
namespace App\Controller;

use App\Controller\AppController;
use Cake\Core\Configure;
use Cake\Event\Event;

/**
 * Developer controller handling developer login/logout/register
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
 * Developer controller handling developer login/logout/register
 *
 * @package       Server.Controller
 */
class DevelopersController extends AppController {

	public $helpers = array('Html', 'Form');

	public $components = array(
		'GithubApi',
	);

	public function beforeFilter(Event $event) {
		$this->GithubApi->githubConfig = Configure::read('GithubConfig');
		$this->GithubApi->githubRepo = Configure::read('GithubRepoPath');
        error_log(json_encode($this->GithubApi->githubConfig));
	}

	public function login() {
		$url = $this->GithubApi->getRedirectUrl('user:email');
		$this->redirect($url);
	}

	public function callback() {
		$code = $this->request->query('code');
		$accessToken = $this->GithubApi->getAccessToken($code);
		if ($accessToken) {
			list($userInfo, $status) = $this->GithubApi->getUserInfo($accessToken);
			if ($status != 200) {
				$this->Session->setFlash($userInfo['message'],
						array("class" => "alert alert-error"));
			} else {
				$userInfo["has_commit_access"] = $this->GithubApi->canCommitTo(
						$userInfo["login"], $this->GithubApi->githubRepo);

				$this->_authenticateDeveloper($userInfo, $accessToken);

				$this->Flash->default("You have been logged in successfully",
						array("class" => "alert alert-success"));
			}
		} else {
			$this->Flash->default("We were not able to authenticate you."
					. "Please try again later",
					array("class" => "alert alert-error"));
		}
		$last_page = $this->request->session()->read("last_page");
		if(empty($last_page)) {
			$last_page = array("controller" => "reports","action" => "index");
		}
		$this->redirect($last_page);
	}

	public function logout() {
		$this->request->session()->destroy();
		$this->Flash->default("You have been logged out successfully",
				array("class" => "alert alert-success"));
		$this->redirect("/");
	}

	public function currentDeveloper() {
		$this->autoRender = false;
		return json_encode($this->GithubApi->canCommitTo("smita786",
				"smita786/phpmyadmin"));
	}

	protected function _authenticateDeveloper($userInfo, $accessToken) {
		$developers = $this->Developers->findByGithubId($userInfo['id']);
        $developer = $developers->all()->first();
		if (!$developer) {
			$developer = $this->Developers->newEntity();
		} else {
			$this->Developers->id = $developer["id"];
        }
		$this->Developers->id = $this->Developers->saveFromGithub($userInfo, $accessToken, $developer);
		$this->request->session()->write("Developer.id", $this->Developers->id);
	}
}
