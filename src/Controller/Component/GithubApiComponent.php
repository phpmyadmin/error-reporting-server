<?php

/**
 * Github api component handling comunication with github.
 *
 * phpMyAdmin Error reporting server
 * Copyright (c) phpMyAdmin project (https://www.phpmyadmin.net/)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) phpMyAdmin project (https://www.phpmyadmin.net/)
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 *
 * @see      https://www.phpmyadmin.net/
 */

namespace App\Controller\Component;

use Cake\Controller\Component;
use Cake\Routing\Router;
use const CURLINFO_HTTP_CODE;
use const CURLOPT_CUSTOMREQUEST;
use const CURLOPT_HTTPHEADER;
use const CURLOPT_POSTFIELDS;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_USERAGENT;
use function array_merge;
use function curl_init;
use function curl_setopt;
use function http_build_query;
use function json_decode;
use function json_encode;
use function strtoupper;

/**
 * Github api component handling comunication with github.
 */
class GithubApiComponent extends Component
{
    /**
     * perform an api request given a path, the data to send, the method and whether
     * or not to return a status.
     *
     * @param string       $path         the api path to preform the request to
     * @param array|string $data         the data to send in the request. This works with both GET
     *                            and Post requests
     * @param string       $method       the method type of the request
     * @param bool         $returnStatus whether to return the status code with the
     *                                   request
     * @param string       $access_token the github access token
     *
     * @return array the returned response decoded and optionally the status code,
     *               see GithubApiComponent::sendRequest()
     *
     * @see GithubApiComponent::sendRequest()
     */
    public function apiRequest(
        string $path = '',
        $data = [],
        string $method = 'GET',
        bool $returnStatus = false,
        string $access_token = ''
    ): array {
        $path = 'https://api.github.com/' . $path;
        if (strtoupper($method) === 'GET') {
            $path .= '?' . http_build_query($data);
            $data = [];
        }

        return $this->sendRequest($path, $data, $method, $returnStatus, $access_token);
    }

    /**
     * retrieve an access token using a code that has been authorized by a user.
     *
     * @param string $code the code returned by github to the callback url
     *
     * @return string|null the access token
     */
    public function getAccessToken(?string $code): ?string
    {
        $url = 'https://github.com/login/oauth/access_token';
        $data = array_merge(
            $this->githubConfig,
            ['code' => $code]
        );
        $decodedResponse = $this->sendRequest($url, http_build_query($data), 'POST');

        return $decodedResponse['access_token'];
    }

    /**
     * retrieve the github info stored on a user by his access token.
     *
     * @param string $accessToken the access token belonging to the user being
     *                            requested
     *
     * @return array the github info returned by github as an associative array
     */
    public function getUserInfo(string $accessToken): array
    {
        return $this->apiRequest('user', [], 'GET', true, $accessToken);
    }

    /**
     * perform an http request using curl given a url, the post data to send, the
     * request method and whether or not to return a status.
     *
     * @param string       $url          the url to preform the request to
     * @param array|string $data         the post data to send in the request. This only works with POST requests. GET requests need the data appended in the url.
     *                            with POST requests. GET requests need the data appended
     *                            in the url.
     * @param string       $method       the method type of the request
     * @param bool         $returnCode   whether to return the status code with the
     *                                   request
     * @param string       $access_token the github access token
     *
     * @return array the returned response decoded and optionally the status code,
     *               eg: array($decodedResponse, $statusCode) or just $decodedResponse
     */
    public function sendRequest(
        string $url,
        $data,
        string $method,
        bool $returnCode = false,
        string $access_token = ''
    ): array {
        $curlHandle = curl_init($url);
        curl_setopt($curlHandle, CURLOPT_CUSTOMREQUEST, $method);
        $header = ['Accept: application/json'];
        if (isset($access_token) && $access_token !== '') {
            $header[] = 'Authorization: token ' . $access_token;
        }
        curl_setopt($curlHandle, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curlHandle, CURLOPT_USERAGENT, 'phpMyAdmin - Error Reporting Server');
        curl_setopt($curlHandle, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($curlHandle);// phpcs:ignore SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFallbackGlobalName
        $decodedResponse = json_decode($response, true);
        if ($returnCode) {
            $status = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);// phpcs:ignore SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFallbackGlobalName
            // phpcs ignored patterns for mock testing reasons

            return [
                $decodedResponse,
                $status,
            ];
        }

        return $decodedResponse;
    }

    /**
     * generate the url to redirect the user to for authorization given the
     * requested scope.
     *
     * @param string $scope the api scope for the user to authorize
     *
     * @return string the generated url to redirect the user to
     */
    public function getRedirectUrl(?string $scope = null): string
    {
        $url = 'https://github.com/login/oauth/authorize';
        $data = [
            'client_id' => $this->githubConfig['client_id'],
            'redirect_uri' => Router::url(
                [
                    'controller' => 'developers',
                    'action' => 'callback',
                ],
                true
            ),
            'scope' => $scope,
        ];

        return $url . '?' . http_build_query($data);
    }

    /**
     * Check if a user can commit to a rep.
     *
     * @param string $username     the username to check
     * @param string $repoPath     the repo path of the repo to check for
     * @param string $access_token the github access token
     *
     * @return bool true if the user is a collaborator and false if they arent
     */
    public function canCommitTo(string $username, string $repoPath, string $access_token): bool
    {
        [, $status] = $this->apiRequest(
            'repos/' . $repoPath . '/collaborators/' . $username,
            [],
            'GET',
            true,
            $access_token
        );

        return $status === 204;
    }

    /**
     * make api request for github issue creation.
     *
     * @param string $repoPath     The repo slug
     * @param array  $data         issue details
     * @param string $access_token the github access token
     * @return array
     */
    public function createIssue(string $repoPath, array $data, string $access_token): array
    {
        return $this->apiRequest(
            'repos/' . $repoPath . '/issues',
            json_encode($data),
            'POST',
            true,
            $access_token
        );
    }

    /**
     * make api request for github comment creation.
     *
     * @param string $repoPath     The repo slug
     * @param array  $data         The data
     * @param int    $issueNumber  The issue number
     * @param string $access_token The github access token
     * @return array
     */
    public function createComment(string $repoPath, array $data, int $issueNumber, string $access_token): array
    {
        return $this->apiRequest(
            'repos/' . $repoPath . '/issues/' . $issueNumber . '/comments',
            json_encode($data),
            'POST',
            true,
            $access_token
        );
    }

    /**
     * Make API request for getting Github issue's status
     *
     * @param string $repoPath     The repo slug
     * @param array  $data         The data
     * @param int    $issueNumber  The issue number
     * @param string $access_token The github access token
     * @return array
     */
    public function getIssue(string $repoPath, array $data, int $issueNumber, string $access_token): array
    {
        return $this->apiRequest(
            'repos/' . $repoPath . '/issues/' . $issueNumber,
            $data,
            'GET',
            true,
            $access_token
        );
    }
}
