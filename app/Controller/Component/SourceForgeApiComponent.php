<?php
define('REQUEST_TOKEN_URL', 'https://sourceforge.net/rest/oauth/request_token');
define('ACCESS_TOKEN_URL', 'https://sourceforge.net/rest/oauth/access_token');
define('AUTHORIZE_URL', 'https://sourceforge.net/rest/oauth/authorize');

App::import('Vendor', 'OAuth/OAuthClient');
App::uses('Component', 'Controller');
class SourceForgeApiComponent extends Component {
	public function createClient() {
		$source_forge_config = Configure::read('SourceForgeConfig');
		return new OAuthClient($source_forge_config['consumer_key'],
				$source_forge_config['consumer_secret']);
	}

	public function getAccessToken($request_token) {
		$client = $this->createClient();
		return $client->getAccessToken(ACCESS_TOKEN_URL, $request_token);
	}

	public function getRequestToken($callback_action) {
		$client = $this->createClient();
		$callback_url = 'http://' . $_SERVER['HTTP_HOST'] . $callback_action;
		return $client->getRequestToken(REQUEST_TOKEN_URL, $callback_url);
	}

	public function getRedirectUrl($request_token) {
		return AUTHORIZE_URL . '?oauth_token=' . $request_token->key;
	}

	public function createTicket($project, $data) {
		$client = $this->createClient();
		$access_token = $this->access_token;
		return $client->post($access_token['key'], $access_token['secret'],
				"https://sourceforge.net/rest/p/$project/bugs/new", $data);
	}
}
