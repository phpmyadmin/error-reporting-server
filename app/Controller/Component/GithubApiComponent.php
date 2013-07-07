<?php
App::uses('Component', 'Controller');
class GithubApiComponent extends Component {
  public function api_request($url = "/", $data = array(), $method = "GET") {
    $url = "https://api.github.com" . $url;
    if(strtoupper($method) === "GET") {
      $url .= "?" . http_build_query($data);
    }
    return $this->send_request($url, $data, $method);
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

  public function send_request($url, $data, $method) {
    $curl_handle = curl_init($url);
    curl_setopt($curl_handle, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($curl_handle, CURLOPT_HTTPHEADER, array('Accept: application/json'));
    curl_setopt($curl_handle, CURLOPT_USERAGENT, 'PHP My Admin - Error Reporting Server');
    curl_setopt($curl_handle, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($curl_handle);
    $decoded_response = json_decode($response, true);
    return $decoded_response;
  }
}
