<?php

/**
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

namespace App\Github;

use Cake\Core\Configure;
use Cake\Http\Client;
use Cake\Http\Client\Request as ClientRequest;
use Cake\Http\Client\Response as ClientResponse;
use Cake\Log\Log;

use function assert;
use function in_array;
use function is_array;
use function is_string;
use function json_encode;

/**
 * Talks to the GitHub API.
 *
 * The controllers reach it through GithubApiComponent and the middleware uses
 * it directly, so the client, the requests and the reading of a permission
 * live here once.
 */
class GithubApi
{
    /**
     * Roles of a repository that are allowed to act on the reports.
     */
    public const WRITE_ROLES = ['admin', 'maintain', 'write'];

    /**
     * Seconds allowed to open the connection to the API.
     */
    public const CONNECT_TIMEOUT = 3;

    /**
     * Seconds allowed for a whole request to the API.
     */
    public const TIMEOUT = 5;

    /** Built on first use, then reused for every request of this instance. */
    private Client|null $client = null;

    /** The answer of the last request, for the details of a refusal. */
    private ClientResponse|null $lastResponse = null;

    /** @param Client|null $client Client to use, one is built when absent */
    public function __construct(Client|null $client = null)
    {
        $this->client = $client;
    }

    /**
     * The HTTP client of every request.
     */
    public function getClient(): Client
    {
        return $this->client ??= new Client([
            'redirect' => 5,// Max redirects to follow
            'timeout' => self::TIMEOUT,
            'ssl_verify_peer' => true,
        ]);
    }

    /**
     * Replace the HTTP client, for a test.
     *
     * @param Client $client The client to use
     */
    public function setClient(Client $client): void
    {
        $this->client = $client;
    }

    /**
     * Perform an HTTP request to the API and read its answer.
     *
     * @param string                      $url         the path, appended to the API url
     * @param array<string, mixed>|string $data        the post data to send. GET requests need
     *                                         the data appended in the url.
     * @param string                      $method      the method type of the request
     * @param bool                        $returnCode  whether to return the status code too
     * @param string                      $accessToken the github access token
     * @phpstan-param ClientRequest::METHOD_* $method
     *
     * @return array<mixed> the decoded response, and the status code when asked
     *                      for, eg: [$decodedResponse, $statusCode]
     */
    public function apiRequest(
        string $url,
        array|string $data,
        string $method,
        bool $returnCode = false,
        string $accessToken = '',
    ): array {
        return $this->sendRequest(
            'https://api.github.com/' . $url,
            $data,
            $method,
            $returnCode,
            $accessToken,
        );
    }

    /**
     * Perform a request to a github url.
     *
     * @param string                      $url         the url to preform the request to
     * @param array<string, mixed>|string $data        the post data to send. GET requests need
     *                                         the data appended in the url.
     * @param string                      $method      the method type of the request
     * @param bool                        $returnCode  whether to return the status code too
     * @param string                      $accessToken the github access token
     * @phpstan-param ClientRequest::METHOD_* $method
     *
     * @return array<mixed> the decoded response, and the status code when asked for
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
        $response = $this->getClient()->sendRequest($request);
        assert($response instanceof ClientResponse);

        $this->lastResponse = $response;
        $status = $response->getStatusCode();

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
     * Exchange the code of the callback for an access token.
     *
     * @param string $code The code github sends back
     *
     * @return string|null the access token, null when it was refused
     */
    public function getAccessToken(string $code): string|null
    {
        $config = (array) Configure::read('GithubConfig');

        [$result, $statusCode] = $this->sendRequest(
            'https://github.com/login/oauth/access_token',
            [
                'client_id' => $config['client_id'] ?? '',
                'client_secret' => $config['client_secret'] ?? '',
                'code' => $code,
                // The state is read from the session by the caller: it is not
                // part of the exchange, and reading it here warned whenever the
                // configuration did not carry the key.
                'state' => $config['state'] ?? '',
            ],
            ClientRequest::METHOD_POST,
            true,
        );

        if ($statusCode !== 200) {
            Log::error(
                'Github api request to get access token ended in a status code: ' . $statusCode
                . ' and data ' . json_encode($result),
            );

            return null;
        }

        if (! is_array($result) || ! isset($result['access_token']) || ! is_string($result['access_token'])) {
            Log::error(
                'Github api request to get access token returned no token and data '
                . json_encode($result),
            );

            return null;
        }

        return $result['access_token'];
    }

    /**
     * Read the account of the token.
     *
     * @param string $accessToken the github access token
     *
     * @return array<mixed> the github info returned by github
     */
    public function getUserInfo(string $accessToken): array
    {
        return $this->apiRequest('user', [], ClientRequest::METHOD_GET, true, $accessToken);
    }

    /**
     * Check if a user can commit to a repository.
     *
     * Only the permissions that allow writing are accepted. The collaborators
     * endpoint cannot be used for this: it answers 204 for every collaborator,
     * read only and triage ones included.
     *
     * Null is returned when the answer cannot be known, so that a caller which
     * already held an answer keeps it.
     *
     * @param string $username     the username to check
     * @param string $repoPath     the repo path of the repo to check for
     * @param string $access_token the github access token
     *
     * @return bool|null True when the user may write, null when the API could
     *                   not be asked
     */
    public function canCommitTo(string $username, string $repoPath, string $access_token): bool|null
    {
        if ($username === '' || $repoPath === '' || $access_token === '') {
            // Nothing to ask with: the answer is unknown, not a refusal.
            return null;
        }

        [$role, $status] = $this->getCollaboratorPermission($username, $repoPath, $access_token);

        // A refusal of the token is not a refusal of the developer: the token
        // of a login only carries the scopes the user agreed to share, and the
        // endpoint refuses a read-only account as well. Ask a second time with
        // the token of the server, which is not tied to what a user gave.
        $refused = false;
        if ($status !== 200) {
            $refused = $status === 401 || ($status === 403 && ! $this->isRateLimited());
            $serverToken = (string) Configure::read('GithubAccessToken');
            if ($serverToken !== '' && $serverToken !== $access_token) {
                [$role, $status] = $this->getCollaboratorPermission($username, $repoPath, $serverToken);
                $refused = $status === 401 || ($status === 403 && ! $this->isRateLimited());
            }
        }

        if ($status === 200 && $role !== null) {
            return in_array($role, self::WRITE_ROLES, true);
        }

        Log::error(
            'Collaborator permission call for ' . $username . ' on ' . $repoPath
            . ' ended in a status code: ' . $status,
        );

        if ($status === 200) {
            // A 200 without a role: the answer is there, and it grants nothing.
            return false;
        }

        if ($status === 404) {
            // Not a collaborator of the repository.
            return false;
        }

        // The API refused every token it was given: the developer has no
        // access. Only an answer that says nothing, a transport error, a
        // server error or a rate limit, leaves the question open.
        return $refused ? false : null;
    }

    /**
     * Whether the last answer of the API was a rate limit rather than a
     * refusal.
     */
    protected function isRateLimited(): bool
    {
        $response = $this->lastResponse;

        return $response !== null
            && $response->getHeaderLine('X-RateLimit-Remaining') === '0';
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
}
