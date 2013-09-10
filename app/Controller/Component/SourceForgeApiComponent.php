<?php
/* vim: set noexpandtab sw=2 ts=2 sts=2: */
/**
 * Source forge api component handling comunication with source forge
 *
 * phpMyAdmin Error reporting server
 * Copyright (c) phpMyAdmin project (http://www.phpmyadmin.net)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) phpMyAdmin project (http://www.phpmyadmin.net)
 * @package       Server.Controller.Component
 * @link          http://www.phpmyadmin.net
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

define('REQUEST_TOKEN_URL', 'https://sourceforge.net/rest/oauth/request_token');
define('ACCESS_TOKEN_URL', 'https://sourceforge.net/rest/oauth/access_token');
define('AUTHORIZE_URL', 'https://sourceforge.net/rest/oauth/authorize');

App::import('Vendor', 'OAuth/OAuthClient');
App::uses('Component', 'Controller');

/**
 * SourceForge.net api component handling comunication with SourceForge.net
 *
 * @package       Server.Controller.Component
 */
class SourceForgeApiComponent extends Component {

/**
 * creates a new client using the consumer secret and key in configuration files
 *
 * @return OAuthClient the client which can then be used in an authentication
 *                     request
 */
	public function createClient() {
		$sourceForgeConfig = Configure::read('SourceForgeConfig');
		return new OAuthClient($source_forge_config['consumer_key'],
				$sourceForgeConfig['consumer_secret']);
	}

/**
 * requests an access token from SourceForge.net from a request token. The request
 * token needs to be authorized by the user. You need to redirect the user to
 * the SourceForge.net authorization url which you can get through
 * SourceForgeApiComponent::getRedirectUrl($requestToken)
 *
 * @param  OAuthToken $requestToken that has been authorized by the user
 * @return OAuthToken access token returned by SourceForge.net which can then
 *                    be used in api requests
 */
	public function getAccessToken($requestToken) {
		$client = $this->createClient();
		return $client->getAccessToken(ACCESS_TOKEN_URL, $requestToken);
	}

/**
 * requests a request token from SourceForge.net using a callback url for the user
 * to return to.
 *
 * @param  String     $callbackAction the action path to redirect the user to after
 *                                    authorizing the token
 * @return OAuthToken request token returned by SourceForge.net which can then be
 *                    authorized by a user.
 */
	public function getRequestToken($callbackAction) {
		$client = $this->createClient();
		$callbackUrl = 'http://' . $_SERVER['HTTP_HOST'] . $callbackAction;
		return $client->getRequestToken(REQUEST_TOKEN_URL, $callbackUrl);
	}

/**
 * generates the url to redirect the user to authorize the request token
 *
 * @param  OAuthToken $requestToken the request token to be authorized.
 * @return String the url to redirect the user to.
 */
	public function getRedirectUrl($requestToken) {
		return AUTHORIZE_URL . '?oauth_token=' . $requestToken->key;
	}

/**
 * Submits a create ticket request to the SourceForge.net api. It uses the access
 * token that must be set as a property on this component, through
 * SourceForgeApiComponent->accessToken
 *
 * @param  String $project the project name of the SourceForge.net project to submit
 *                         the ticket to.
 * @param  Array  $data the ticket data to submit.
 * @return Array  the response returned by SourceForge.net
 */
	public function createTicket($project, $data) {
		$client = $this->createClient();
		$accessToken = $this->accessToken;
		return $client->post($accessToken['key'], $accessToken['secret'],
				"https://sourceforge.net/rest/p/$project/bugs/new", $data);
	}
}
