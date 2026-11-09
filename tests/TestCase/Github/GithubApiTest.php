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
use Cake\Http\Client;
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
    protected function clientAnswering(int $status, array|null $json): Client
    {
        $response = new Response(
            ['HTTP/1.1 ' . $status . ' Answer', 'Content-Type: application/json'],
            (string) json_encode($json),
        );

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
            'a forbidden answer' => [403, null, null],
        ];
    }

    #[DataProvider('roleProvider')]
    public function testTheRoleDecidesTheAccess(int $status, array|null $json, bool|null $expected): void
    {
        $result = (new GithubApi($this->clientAnswering($status, $json)))->canCommitTo(
            'pma-bot',
            'phpmyadmin/phpmyadmin',
            'token',
        );

        $this->assertSame($expected, $result);
    }

    public function testNoTokenIsNotAnAnswer(): void
    {
        $this->assertNull((new GithubApi())->canCommitTo('pma-bot', 'phpmyadmin/phpmyadmin', ''));
        $this->assertNull((new GithubApi())->canCommitTo('', 'phpmyadmin/phpmyadmin', 'token'));
    }
}
