<?php
App::uses('Component', 'Controller');
class GithubApiComponent extends Component {
	public function api_request($url = "/", $data = array(), $method = "GET",
			$return_status=false) {
		$url = "https://api.github.com" . $url;
		if(strtoupper($method) === "GET") {
			$url .= "?" . http_build_query($data);
			$data = array();
		}
		return $this->send_request($url, $data, $method, $return_status);
	}

	public function get_access_token($code) {
		$url = "https://github.com/login/oauth/access_token";
		$data = array_merge(
			$this->github_config,
			array(
				'code' => $code,
			)
		);
		$decoded_response = $this->send_request($url, $data, "POST");
		return $decoded_response['access_token'];
	}

	public function get_user_info($access_token) {
		$url = "/user";
		$data = array(
			'access_token' => $access_token,
		);
		return $this->api_request($url, $data, "GET");
	}

	public function send_request($url, $data, $method, $return_code=false) {
		$curl_handle = curl_init($url);
		curl_setopt($curl_handle, CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($curl_handle, CURLOPT_HTTPHEADER, array('Accept: application/json'));
		curl_setopt($curl_handle, CURLOPT_USERAGENT, 'PHP My Admin - Error Reporting Server');
		curl_setopt($curl_handle, CURLOPT_POSTFIELDS, http_build_query($data));
		curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1);
		$response = curl_exec($curl_handle);
		$decoded_response = json_decode($response, true);
		if($return_code) {
			$status = curl_getinfo($curl_handle, CURLINFO_HTTP_CODE);
			return array($decoded_response, $status);
		} else {
			return $decoded_response;
		}
	}
	
	public function get_redirect_url($scope) {
		$url = "https://github.com/login/oauth/authorize";
		$data = array(
			'client_id' => $this->github_config['client_id'],
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

	public function can_commit_to($username, $repo_path) {
		list($response, $status) = $this->
				api_request("/repos/$repo_path/collaborators/$username",
				array(), "GET", true);
		return $status === 204;
	}
}
