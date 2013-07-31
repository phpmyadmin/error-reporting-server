<?php
/* vim: set noexpandtab sw=2 ts=2 sts=2: */

App::uses('Component', 'Controller');
class GithubApiComponent extends Component {

	public function apiRequest($url = "/", $data = array(), $method = "GET",
			$returnStatus=false) {
		$url = "https://api.github.com" . $url;
		if (strtoupper($method) === "GET") {
			$url .= "?" . http_build_query($data);
			$data = array();
		}
		return $this->sendRequest($url, $data, $method, $returnStatus);
	}

	public function getAccessToken($code) {
		$url = "https://github.com/login/oauth/access_token";
		$data = array_merge(
			$this->githubConfig,
			array(
				'code' => $code,
			)
		);
		$decodedResponse = $this->sendRequest($url, $data, "POST");
		return $decodedResponse['access_token'];
	}

	public function getUserInfo($access_token) {
		$url = "/user";
		$data = array(
			'access_token' => $access_token,
		);
		return $this->apiRequest($url, $data, "GET");
	}

	public function sendRequest($url, $data, $method, $returnCode=false) {
		$curlHandle = curl_init($url);
		curl_setopt($curlHandle, CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($curlHandle, CURLOPT_HTTPHEADER, array('Accept: application/json'));
		curl_setopt($curlHandle, CURLOPT_USERAGENT, 'PHP My Admin - Error Reporting Server');
		curl_setopt($curlHandle, CURLOPT_POSTFIELDS, http_build_query($data));
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
	
	public function getRedirectUrl($scope) {
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

	public function canCommitTo($username, $repoPath) {
		list($response, $status) = $this->
				apiRequest("/repos/$repoPath/collaborators/$username",
				array(), "GET", true);
		return $status === 204;
	}
}
