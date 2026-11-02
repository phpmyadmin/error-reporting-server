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
use Cake\Core\Configure;
use Cake\Http\Client;
use Cake\Http\Client\Request as ClientRequest;
use Cake\Http\Client\Response as ClientResponse;
use Cake\Log\Log;
use Cake\Routing\Router;

use function array_merge;
use function assert;
use function http_build_query;
use function in_array;
use function is_array;
use function is_string;
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
     * @see GithubApiComponent::sendRequest()
     *
     * @param string       $path         the api path to preform the request to
     * @param array|string $data         the data to send in the request. This works with both GET
     *                            and Post requests
     * @param string       $method       the method type of the request
     * @param bool         $returnStatus whether to return the status code with the
     *                                   request
     * @param string       $access_token the github access token
     * @phpstan-param ClientRequest::METHOD_* $method
     *
     * @return array the returned response decoded and optionally the status code,
     *               see GithubApiComponent::sendRequest()
     */
    public function apiRequest(
        string $path,
        array|string $data,
        string $method,
        bool $returnStatus = false,
        string $access_token = '',
    ): array {
        $path = 'https://api.github.com/' . $path;
        if (strtoupper($method) === ClientRequest::METHOD_GET) {
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
    public function getAccessToken(string|null $code): string|null
    {
        $url = 'https://github.com/login/oauth/access_token';
        $data = array_merge(
            Configure::read('GithubConfig', []),
            ['code' => $code],
        );
        $decodedResponse = $this->sendRequest($url, http_build_query($data), ClientRequest::METHOD_POST);

        return $decodedResponse['access_token'] ?? null;
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
        return $this->apiRequest('user', [], ClientRequest::METHOD_GET, true, $accessToken);
    }

    /**
     * perform an http request using curl given a url, the post data to send, the
     * request method and whether or not to return a status.
     *
     * @param string       $url         the url to preform the request to
     * @param array|string $data        the post data to send in the request. This only works with POST requests. GET requests need the data appended in the url.
     *                           with POST requests. GET requests need the data appended
     *                           in the url.
     * @param string       $method      the method type of the request
     * @param bool         $returnCode  whether to return the status code with the
     *                                  request
     * @param string       $accessToken the github access token
     * @phpstan-param ClientRequest::METHOD_* $method
     *
     * @return array the returned response decoded and optionally the status code,
     *               eg: array($decodedResponse, $statusCode) or just $decodedResponse
     */
    public function sendRequest(
        string $url,
        array|string $data,
        string $method,
        bool $returnCode = false,
        string $accessToken = '',
    ): array {
        Log::debug('Request-url: ' . $url);

        $headers = [
            'Accept' => 'application/json',
            'User-Agent' => 'phpMyAdmin - Error Reporting Server',
        ];
        if ($accessToken !== '') {
            $headers['Authorization'] = 'token ' . $accessToken;
        }

        $request = new ClientRequest(
            url: $url,
            method: $method,
            headers: $headers,
            data: $data,
        );
        $http = new Client([
            'redirect' => 5,// Max redirects to follow
        ]);
        $response = $http->sendRequest($request);

        $status = $response->getStatusCode();
        assert($response instanceof ClientResponse);

        Log::debug('Response-code: ' . $status . ' for: ' . $url);

        $decodedResponse = $response->getJson();
        if ($status === 401) {
            Log::error('[GitHub] error ' . $status . ' on ' . $url . ' and data ' . json_encode($decodedResponse) . ' maybe the token is expired ?');
        }

        if ($returnCode) {
            return [
                $decodedResponse,
                $status,
            ];
        }

        return $decodedResponse;
    }

    /**
     * generate the url to redirect the user to for authorization given the
     * requested scope and the OAuth state.
     *
     * @param string|null $scope the api scope for the user to authorize
     * @param string|null $state the OAuth state used to protect the callback
     *                           against login CSRF
     *
     * @return string the generated url to redirect the user to
     */
    public function getRedirectUrl(string|null $scope = null, string|null $state = null): string
    {
        $url = 'https://github.com/login/oauth/authorize';
        $data = [
            'client_id' => Configure::read('GithubConfig', ['client_id' => ''])['client_id'],
            'redirect_uri' => Router::url(
                [
                    'controller' => 'developers',
                    'action' => 'callback',
                ],
                true,
            ),
            'scope' => $scope,
        ];
        if ($state !== null) {
            $data['state'] = $state;
        }

        return $url . '?' . http_build_query($data);
    }

    /**
     * Check if a user can commit to a rep.
     *
     * Only the permissions that allow writing to the repository are accepted.
     * The collaborators endpoint cannot be used for this: it answers 204 for
     * every collaborator, read only and triage ones included.
     *
     * @param string $username     the username to check
     * @param string $repoPath     the repo path of the repo to check for
     * @param string $access_token the github access token
     *
     * @return bool true if the user can write to the repository and false if they cannot
     */
    public function canCommitTo(string $username, string $repoPath, string $access_token): bool
    {
        [$role, $status] = $this->getCollaboratorPermission($username, $repoPath, $access_token);

        if ($status !== 200) {
            // The token of a login only carries the scopes the user agreed to
            // share, and the endpoint refuses a read-only account as well:
            // ask a second time with the token of the server, which is not
            // tied to what a user agreed to give.
            $serverToken = (string) Configure::read('GithubAccessToken');
            if ($serverToken !== '' && $serverToken !== $access_token) {
                [$role, $status] = $this->getCollaboratorPermission($username, $repoPath, $serverToken);
            }
        }

        if ($status !== 200) {
            Log::error(
                'Collaborator permission call for ' . $username . ' on ' . $repoPath
                . ' ended in a status code: ' . $status,
            );

            return false;
        }

        if ($role === null) {
            Log::error('Collaborator permission call for ' . $username . ' on ' . $repoPath . ' returned no data');

            return false;
        }

        return in_array($role, ['admin', 'maintain', 'write'], true);
    }

    /**
     * Read the permission of a user on a repository.
     *
     * @param string $username     GitHub login of the user
     * @param string $repoPath     The repo slug
     * @param string $access_token Token used to ask the API
     *
     * @return array{string|null, int} The role of the user, and the status code
     */
    private function getCollaboratorPermission(string $username, string $repoPath, string $access_token): array
    {
        [$permission, $status] = $this->apiRequest(
            'repos/' . $repoPath . '/collaborators/' . $username . '/permission',
            [],
            ClientRequest::METHOD_GET,
            true,
            $access_token,
        );

        if (! is_array($permission)) {
            return [null, $status];
        }

        $role = $permission['permission'] ?? $permission['role_name'] ?? null;

        return [is_string($role) ? $role : null, $status];
    }

    /**
     * make api request for github issue creation.
     *
     * @param string $repoPath     The repo slug
     * @param array  $data         issue details
     * @param string $access_token the github access token
     */
    public function createIssue(string $repoPath, array $data, string $access_token): array
    {
        return $this->apiRequest(
            'repos/' . $repoPath . '/issues',
            json_encode($data),
            ClientRequest::METHOD_POST,
            true,
            $access_token,
        );
    }

    /**
     * make api request for github comment creation.
     *
     * @param string $repoPath     The repo slug
     * @param array  $data         The data
     * @param int    $issueNumber  The issue number
     * @param string $access_token The github access token
     */
    public function createComment(string $repoPath, array $data, int $issueNumber, string $access_token): array
    {
        return $this->apiRequest(
            'repos/' . $repoPath . '/issues/' . $issueNumber . '/comments',
            json_encode($data),
            ClientRequest::METHOD_POST,
            true,
            $access_token,
        );
    }

    /**
     * Make API request for getting Github issue's status
     *
     * @param string $repoPath     The repo slug
     * @param array  $data         The data
     * @param int    $issueNumber  The issue number
     * @param string $access_token The github access token
     */
    public function getIssue(string $repoPath, array $data, int $issueNumber, string $access_token): array
    {
        return $this->apiRequest(
            'repos/' . $repoPath . '/issues/' . $issueNumber,
            $data,
            ClientRequest::METHOD_GET,
            true,
            $access_token,
        );
    }
}
