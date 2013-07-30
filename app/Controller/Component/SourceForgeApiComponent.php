<?php
define('REQUEST_TOKEN_URL', 'https://sourceforge.net/rest/oauth/request_token');
define('ACCESS_TOKEN_URL', 'https://sourceforge.net/rest/oauth/access_token');
define('AUTHORIZE_URL', 'https://sourceforge.net/rest/oauth/authorize');

App::import('Vendor', 'OAuth/OAuthClient');
App::uses('Component', 'Controller');
class SourceForgeApiComponent extends Component {
	public function createClient() {
		$sourceForgeConfig = Configure::read('SourceForgeConfig');
		return new OAuthClient($source_forge_config['consumer_key'],
				$sourceForgeConfig['consumer_secret']);
	}

	public function getAccessToken($request_token) {
		$client = $this->createClient();
		return $client->getAccessToken(ACCESS_TOKEN_URL, $request_token);
	}

	public function getRequestToken($callbackAction) {
		$client = $this->createClient();
		$callbackUrl = 'http://' . $_SERVER['HTTP_HOST'] . $callbackAction;
		return $client->getRequestToken(REQUEST_TOKEN_URL, $callbackUrl);
	}

	public function getRedirectUrl($requestToken) {
		return AUTHORIZE_URL . '?oauth_token=' . $requestToken->key;
	}

	public function createTicket($project, $data) {
		$client = $this->createClient();
		$accessToken = $this->accessToken;
		return $client->post($accessToken['key'], $accessToken['secret'],
				"https://sourceforge.net/rest/p/$project/bugs/new", $data);
	}
}
