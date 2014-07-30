<?php
/* vim: set noexpandtab sw=2 ts=2 sts=2: */
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
		'Session'
	);

	public function beforeFilter() {
		$this->GithubApi->githubConfig = Configure::read('GithubConfig');
		$this->GithubApi->githubRepo = Configure::read('GithubRepoPath');
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

				$this->Session->setFlash("You have been logged in successfully",
						"default", array("class" => "alert alert-success"));
			}
		} else {
			$this->Session->setFlash("We were not able to authenticate you."
					. "Please try again later", "default",
					array("class" => "alert alert-error"));
		}
		$last_page = $this->Session->read("last_page");
		if(empty($last_page)) {
			$last_page = array("controller" => "reports","action" => "index");
		}
		$this->redirect($last_page);
	}

	public function logout() {
		$this->Session->destroy();
		$this->Session->setFlash("You have been logged out successfully", "default",
				array("class" => "alert alert-success"));
		$this->redirect("/");
	}

	public function currentDeveloper() {
		$this->autoRender = false;
		return json_encode($this->GithubApi->canCommitTo("m0hamed",
				"m0hamed/phpmyadmin"));
	}

	protected function _authenticateDeveloper($userInfo, $accessToken) {
		$developer = $this->Developer->findByGithubId($userInfo['id']);
		if (!$developer) {
			$this->Developer->create();
		} else {
			$this->Developer->id = $developer["Developer"]["id"];
		}
		$this->Developer->saveFromGithub($userInfo, $accessToken);
		$this->Session->write("Developer.id", $this->Developer->id);
	}
}
