<?php
/* vim: set noexpandtab sw=2 ts=2 sts=2: */
namespace App\Controller\Component;

use Cake\Controller\Component;
use Cake\Routing\Router;
/**
 * Github api component handling comunication with github
 *
 * phpMyAdmin Error reporting server
 * Copyright (c) phpMyAdmin project (https://www.phpmyadmin.net/)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) phpMyAdmin project (https://www.phpmyadmin.net/)
 * @package       Server.Controller.Component
 * @link          https://www.phpmyadmin.net/
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */


/**
 * Github api component handling comunication with github
 *
 * @package       Server.Controller.Component
 */
class GithubApiComponent extends Component {

/**
 * perform an api request given a path, the data to send, the method and whether
 * or not to return a status.
 *
 * @param String  $path the api path to preform the request to
 * @param Array   $data the data to send in the request. This works with both GET
 *                      and Post requests
 * @param String  $method the method type of the request
 * @param Boolean $returnStatus whether to return the status code with the
 *                              request
 * @return Array  the returned response decoded and optionally the status code,
 *                see GithubApiComponent::sendRequest()
 * @see GithubApiComponent::sendRequest()
 */
	public function apiRequest($path = "", $data = array(), $method = "GET",
			$returnStatus=false, $access_token="") {
		$path = "https://api.github.com/" . $path;
		if (strtoupper($method) === "GET") {
			$path .= "?" . $data;
			$data = array();
		}
		return $this->sendRequest($path, $data, $method, $returnStatus, $access_token);
	}

/**
 * retrieve an access token using a code that has been authorized by a user
 *
 * @param String $code the code returned by github to the callback url.
 * @return String the access token
 */
	public function getAccessToken($code) {
		$url = "https://github.com/login/oauth/access_token";
		$data = array_merge(
			$this->githubConfig,
			array(
				'code' => $code,
			)
		);
		$decodedResponse = $this->sendRequest($url, http_build_query($data), "POST");
		return $decodedResponse['access_token'];
	}

/**
 * retrieve the github info stored on a user by his access token
 *
 * @param String $accessToken the access token belonging to the user being
 *                            requested.
 * @return Arrray the github info returned by github as an associative array
 */
	public function getUserInfo($accessToken) {
		$data = array(
			'access_token' => $accessToken,
		);
		return $this->apiRequest("user", http_build_query($data), "GET", true);
	}

/**
 * perform an http request using curl given a url, the post data to send, the
 * request method and whether or not to return a status.
 *
 * @param String  $url the url to preform the request to.
 * @param Array   $data the post data to send in the request. This only works
 *                      with POST requests. GET requests need the data appended
 *	                    in the url.
 * @param String  $method the method type of the request
 * @param Boolean $returnCode whether to return the status code with the
 *                            request
 * @return Array the returned response decoded and optionally the status code,
 *               eg: array($decodedResponse, $statusCode) or just $decodedResponse
 */
	public function sendRequest($url, $data, $method, $returnCode=false, $access_token="") {
		$curlHandle = curl_init($url);
		curl_setopt($curlHandle, CURLOPT_CUSTOMREQUEST, $method);
        $header = array('Accept: application/json');
        if (isset($access_token) && $access_token != "") {
            $header[] = 'Authorization: token '.$access_token;
        }
		curl_setopt($curlHandle, CURLOPT_HTTPHEADER, $header);
		curl_setopt($curlHandle, CURLOPT_USERAGENT, 'phpMyAdmin - Error Reporting Server');
		curl_setopt($curlHandle, CURLOPT_POSTFIELDS, $data);
		curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, 1);
		$response = curl_exec($curlHandle);
		$decodedResponse = json_decode($response, true);
		if ($returnCode) {
			$status = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
			return array($decodedResponse, $status);
		} else {
			return $decodedResponse;
		}
	}

/**
 * generate the url to redirect the user to for authorization given the
 * requested scope.
 *
 * @param  String $scope the api scope for the user to authorize
 * @return String the generated url to redirect the user to
 */
	public function getRedirectUrl($scope=null) {
		$url = "https://github.com/login/oauth/authorize";
		$data = array(
			'client_id' => $this->githubConfig['client_id'],
			'redirect_uri' => Router::url(
				array(
					'controller' => 'developers',
					'action' => 'callback'
				), true
			),
			'scope' => $scope,
		);

		$url .= "?" . http_build_query($data);
		return $url;
	}

/**
 * Check if a user can commit to a rep
 *
 * @param  String $username the username to check
 * @param  String $repoPath the repo path of the repo to check for
 * @return Boolean true if the user is a collaborator and false if they arent
 */
	public function canCommitTo($username, $repoPath) {
		list(, $status) = $this->
				apiRequest("repos/$repoPath/collaborators/$username",
				http_build_query(array()), "GET", true);
		return $status === 204;
	}

    /**
     * make api request for github issue creation
     *
     * @param string $repoPath
     * @param Array $data issue details
     * @param string $access_token
     *
     */
    public function createIssue($repoPath, $data, $access_token) {
        return $this->apiRequest("repos/$repoPath/issues", json_encode($data), "POST", true, $access_token);
    }

    /**
     * make api request for github comment creation
     *
     * @param string $repoPath
     * @param Array $data
     * @param Integer $issueNumber
     * @param string $access_token
     *
     */
    public function createComment($repoPath, $data, $issueNumber, $access_token) {
        return $this->apiRequest("repos/$repoPath/issues/$issueNumber/comments", json_encode($data), "POST", true, $access_token);
    }
}
