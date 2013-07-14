<?php
define('REQUEST_TOKEN_URL', 'https://sourceforge.net/rest/oauth/request_token');
define('AUTHORIZE_URL', 'https://sourceforge.net/rest/oauth/authorize');
define('ACCESS_TOKEN_URL', 'https://sourceforge.net/rest/oauth/access_token');

App::import('Vendor', 'OAuth/OAuthClient');

class SourceForgeController extends AppController {
  public $helpers = array('Html', 'Form');
  public $components = array(
  );

  public function test_auth() {
    $client = $this->createClient();
    $requestToken = $client->getRequestToken(REQUEST_TOKEN_URL, 'http://' .
        $_SERVER['HTTP_HOST'] . '/source_forge/callback', 'GET');
    if($requestToken) {
      $this->Session->write('sourceforge_request_token', $requestToken);
      $this->redirect(AUTHORIZE_URL . '?oauth_token=' . $requestToken->key);
    }
    $this->autoRender = false;
    return json_encode($requestToken);
  }

  public function callback() {
    $requestToken = $this->Session->read('sourceforge_request_token');
    $client = $this->createClient();
    $accessToken = $client->getAccessToken(ACCESS_TOKEN_URL, $requestToken);
    $this->autoRender = false;
    return json_encode($accessToken);
  }

  private function createClient() {
    $source_forge_config = Configure::read('SourceForgeConfig');
    return new OAuthClient($source_forge_config['consumer_key'],
        $source_forge_config['consumer_secret']);
  }

}
