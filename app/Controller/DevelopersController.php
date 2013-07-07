<?php
class DevelopersController extends AppController {
  public $helpers = array('Html', 'Form');
  public $components = array(
    'GithubApi',
    'Session'
  );

  public function beforeFilter() {
    $this->GithubApi->github_config = Configure::read('GithubConfig');
  }

  public function login() {
    $url = "https://github.com/login/oauth/authorize";
    $data = array(
      'client_id' => Configure::read('GithubConfig')['client_id'],
      'redirect_uri' => Router::url(
        array(
          'controller' => 'developers',
          'action' => 'callback'
        ), true
      ),
      'scope' => 'user:email,public_repo',
    );

    $url .= "?" . http_build_query($data);
    $this->redirect($url);
  }

  public function callback() {
    $code = $this->request->query('code');
    $access_token = $this->GithubApi->get_access_token($code);
    $this->autoRender = false;
    if($access_token) {
      $user_info = $this->get_user_info($access_token);
      $this->authenticate_developer($user_info, $access_token);
      return $access_token;
    } else {
      $this->Session->destroy();
      return "No Access Token Returned";
    }
  }

  public function logout() {
    $this->Session->destroy();
    $this->Session->setFlash("You have been logged out successfully");
    $this->redirect(array('controller' => 'reports', 'action' => 'index'));
  }

  public function current_developer() {
    $this->autoRender = false;
    return json_encode($this->Session->read('Developer.id'));
  }

  private function get_user_info($access_token) {
    $url = "/user";
    $data = array(
      'access_token' => $access_token,
    );
    return $this->GithubApi->api_request($url, $data, "GET");
  }

  private function authenticate_developer($user_info, $access_token) {
    if(!$this->Developer->findByGithubId($user_info['id'])) {
      $this->Developer->create();
      $this->Developer->save_from_github($user_info, $access_token);
    }
    $this->Session->write("Developer.id", $this->Developer->id);
  }
}

