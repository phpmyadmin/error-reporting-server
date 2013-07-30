<?php
/* vim: set noexpandtab sw=2 ts=2 sts=2: */

App::uses('Model', 'Model');

class Developer extends Model {

	public function saveFromGithub($githubInfo, $accessToken) {
		$userData = array(
			'full_name' => $githubInfo['name'],
			'gravatar_id' => $githubInfo['gravatar_id'],
			'email' => $githubInfo['email'],
			'github_id' => $githubInfo['id'],
			'access_token' => $accessToken,
			'has_commit_access' => $githubInfo['has_commit_access']?1:0,
		);
		return $this->save($userData);
	}
}
