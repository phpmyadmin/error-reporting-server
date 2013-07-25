<?php
class SourceForgeController extends AppController {
  public $helpers = array('Html', 'Form');
  public $components = array('SourceForgeApi');
  public $uses = array('Report');

  public function beforeFilter() {
    $this->SourceForgeApi->access_token =
        Configure::read('SourceForgeCredentials');
    parent::beforeFilter();
  }

  public function authorize() {
    $requestToken =
        $this->SourceForgeApi->getRequestToken('/source_forge/callback');
    if($requestToken) {
      $this->Session->write('sourceforge_request_token', $requestToken);
      $this->redirect($this->SourceForgeApi->getRedirectUrl($requestToken));
    }
    $this->autoRender = false;
    return json_encode($requestToken);
  }

  public function callback() {
    $requestToken = $this->Session->read('sourceforge_request_token');
    $accessToken = $this->SourceForgeApi->getAccessToken($requestToken);
    $this->autoRender = false;
    return json_encode($accessToken);
  }

  private function createClient() {
    $source_forge_config = Configure::read('SourceForgeConfig');
    return new OAuthClient($source_forge_config['consumer_key'],
        $source_forge_config['consumer_secret']);
  }

}
