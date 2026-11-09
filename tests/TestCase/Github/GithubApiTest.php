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

namespace App\Test\TestCase\Github;

use App\Github\GithubApi;
use Cake\Core\Configure;
use Cake\Http\Client;
use Cake\Http\Client\Exception\ClientException;
use Cake\Http\Client\Response;
use Cake\TestSuite\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

use function json_encode;

/**
 * The role of a developer is read from one answer of the GitHub API.
 */
class GithubApiTest extends TestCase
{
    /**
     * Build a client that answers a canned response, without a connection.
     *
     * @param int                       $status The status code to answer
     * @param array<string, mixed>|null $json   The body, as JSON
     */

    /**
     * @param int                       $status  The status code to answer
     * @param array<string, mixed>|null $json    The body, as JSON
     * @param array<string, string>     $headers Extra headers to answer
     */
    protected function clientAnswering(int $status, array|null $json, array $headers = []): Client
    {
        $lines = ['HTTP/1.1 ' . $status . ' Answer', 'Content-Type: application/json'];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }

        $response = new Response($lines, (string) json_encode($json));

        return new class ($response) extends Client {
            public function __construct(private Response $canned)
            {
            }

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                return $this->canned;
            }
        };
    }

    /** @return array<string, array{int, array<string, string>|null, bool|null}> */
    public static function roleProvider(): array
    {
        return [
            'admin' => [200, ['permission' => 'admin'], true],
            'maintain' => [200, ['permission' => 'maintain'], true],
            'write' => [200, ['permission' => 'write'], true],
            'write as a role_name' => [200, ['role_name' => 'write'], true],
            'read' => [200, ['permission' => 'read'], false],
            'triage' => [200, ['permission' => 'triage'], false],
            'no role' => [200, [], false],
            'a role that is not a string' => [200, ['permission' => 1], false],
            'not a collaborator' => [404, null, false],
            'the API is down' => [500, null, null],
            // A token GitHub refuses is a refusal of the token, not a promise of
            // access: both tokens refused means the developer has none.
            'a forbidden token' => [403, null, false],
            'an unauthorised token' => [401, null, false],
        ];
    }

    #[DataProvider('roleProvider')]
    public function testTheRoleDecidesTheAccess(int $status, array|null $json, bool|null $expected): void
    {
        Configure::delete('GithubAccessToken');
        $result = (new GithubApi($this->clientAnswering($status, $json)))->canCommitTo(
            'pma-bot',
            'phpmyadmin/phpmyadmin',
            'token',
        );

        $this->assertSame($expected, $result);
    }

    /**
     * A rate limit is not a refusal: it is an answer that says nothing about the
     * developer, so the caller keeps what it knew.
     */
    public function testARateLimitIsNotARefusal(): void
    {
        Configure::delete('GithubAccessToken');

        $client = $this->clientAnswering(
            403,
            ['message' => 'API rate limit exceeded'],
            ['X-RateLimit-Remaining' => '0'],
        );

        $this->assertNull((new GithubApi($client))->canCommitTo('pma-bot', 'phpmyadmin/phpmyadmin', 'token'));
    }

    /**
     * The callers run on the request path: a DNS failure or a timeout must leave
     * the question open, not throw a 500 out of the middleware.
     */
    public function testATransportErrorIsNotAnAnswer(): void
    {
        Configure::delete('GithubAccessToken');

        $client = new class extends Client {
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                throw new ClientException('Could not reach api.github.com');
            }
        };

        $this->assertNull((new GithubApi($client))->canCommitTo('pma-bot', 'phpmyadmin/phpmyadmin', 'token'));
    }

    public function testNoTokenIsNotAnAnswer(): void
    {
        $this->assertNull((new GithubApi())->canCommitTo('pma-bot', 'phpmyadmin/phpmyadmin', ''));
        $this->assertNull((new GithubApi())->canCommitTo('', 'phpmyadmin/phpmyadmin', 'token'));
    }
}
