<?php
/* vim: set noexpandtab sw=2 ts=2 sts=2: */

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
		$access_token = $this->GithubApi->get_access_token($code);
		if($access_token) {
			$user_info = $this->GithubApi->getUserInfo($access_token);
			$user_info["has_commit_access"] =
					$this->GithubApi->canCommitTo($user_info["login"], $this->github_repo);

			$this->authenticate_developer($user_info, $access_token);

			$this->Session->setFlash("You have been logged in successfully",
					"default", array("class" => "alert alert-success"));
		} else {
			$this->Session->setFlash("We we not able to authenticate you."
					. "Please try again later", "default",
					array("class" => "alert alert-error"));
		}
		$this->redirect(array("controller"=>"reports","action"=>"index"));
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

	private function authenticate_developer($user_info, $access_token) {
		$developer = $this->Developer->findByGithubId($user_info['id']);
		if(!$developer) {
			$this->Developer->create();
		} else {
			$this->Developer->id = $developer["Developer"]["id"];
		}
		$this->Developer->save_from_github($user_info, $access_token);
		$this->Session->write("Developer.id", $this->Developer->id);
	}
}
