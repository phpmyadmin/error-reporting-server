<?php
App::uses('Model', 'Model');

class Developer extends Model {
  public function save_from_github($github_info, $access_token) {
    $user_data = array(
      'full_name' => $github_info['name'],
      'gravatar_id' => $github_info['gravatar_id'],
      'email' => $github_info['email'],
      'github_id' => $github_info['id'],
      'access_token' => $access_token,
      'has_commit_access' => $github_info['has_commit_access']?1:0,
    );
    return $this->save($user_data);
  }
}
