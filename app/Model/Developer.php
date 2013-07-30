<?php
App::uses('Model', 'Model');

class Developer extends Model {
	public function saveFromGithub($githubInfo, $accessToken) {
		$user_data = array(
			'full_name' => $githubInfo['name'],
			'gravatar_id' => $githubInfo['gravatar_id'],
			'email' => $githubInfo['email'],
			'github_id' => $githubInfo['id'],
			'access_token' => $accessToken,
			'has_commit_access' => $githubInfo['has_commit_access']?1:0,
		);
		return $this->save($user_data);
	}
}
