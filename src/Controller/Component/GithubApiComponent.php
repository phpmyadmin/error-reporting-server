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

use App\Github\GithubApi;
use Cake\Controller\Component;
use Cake\Core\Configure;
use Cake\Http\Client\Request as ClientRequest;
use Cake\Routing\Router;

use function http_build_query;
use function json_encode;

/**
 * Github api component handling comunication with github.
 *
 * The API itself lives in App\Github\GithubApi, which the middleware also
 * uses: this component only gives it to a controller.
 */
class GithubApiComponent extends Component
{
    /**
     * The shared API, built when it is first used.
     */
    private GithubApi|null $api = null;

    /**
     * The API of the component.
     */
    public function getApi(): GithubApi
    {
        return $this->api ??= new GithubApi();
    }

    /**
     * Replace the API, for a test.
     *
     * @param GithubApi $api The API to use
     */
    public function setApi(GithubApi $api): void
    {
        $this->api = $api;
    }

    /**
     * perform an api request given a path, the data to send, the method and
     * whether or not to return a status.
     *
     * @see GithubApi::sendRequest()
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
     *               eg: array($decodedResponse, $statusCode) or just $decodedResponse
     */
    public function apiRequest(
        string $path,
        array|string $data,
        string $method,
        bool $returnStatus = false,
        string $access_token = '',
    ): array {
        return $this->getApi()->apiRequest($path, $data, $method, $returnStatus, $access_token);
    }

    /**
     * Exchange the code of the callback for an access token.
     *
     * @param string $code The code github sends back
     *
     * @return string|null the access token, null when it was refused
     */
    public function getAccessToken(string $code): string|null
    {
        return $this->getApi()->getAccessToken($code);
    }

    /**
     * Read the account of the token.
     *
     * @param string $accessToken the github access token
     *
     * @return array the github info returned by github as an associative array
     */
    public function getUserInfo(string $accessToken): array
    {
        return $this->getApi()->getUserInfo($accessToken);
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
        // A controller cannot keep an older answer: an unanswered check is a
        // refusal.
        return $this->getApi()->canCommitTo($username, $repoPath, $access_token) ?? false;
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
