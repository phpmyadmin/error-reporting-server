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

namespace App\Test\TestCase\Controller;

use App\Model\Table\ReportsTable;
use App\Test\Fixture\ReportsFixture;
use Cake\Core\Configure;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

use function hash_hmac;
use function json_decode;
use function json_encode;
use function str_repeat;

/**
 * The webhook is the only unauthenticated write endpoint: it must not accept a
 * request that does not carry a valid signature.
 */
class EventsSecurityTest extends TestCase
{
    use IntegrationTestTrait;

    protected ReportsTable $Reports;

    public function getFixtures(): array
    {
        return [
            ReportsFixture::class,
        ];
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->Reports = TableRegistry::getTableLocator()->get('Reports');
    }

    /**
     * Request headers that pass every check but the signature.
     *
     * @return string[]
     */
    protected function headersWithoutSignature(): array
    {
        return [
            'User-Agent' => 'GitHub-Hookshot-abcdef',
            'X-GitHub-Event' => 'issues',
        ];
    }

    /**
     * Payload of an issue event that closes the issue linked to the report 4.
     */
    protected function closingPayload(): string
    {
        return json_encode(
            [
                'action' => 'closed',
                'issue' => ['number' => 4],
            ],
        );
    }

    /**
     * Without a signature header the received hash is an empty string, which
     * used to be compared with an empty expected hash.
     */
    public function testAMissingSignatureIsRejected(): void
    {
        $report = $this->Reports->get(4);
        $report->set('status', 'new');
        $this->Reports->save($report);

        $this->configRequest(['headers' => $this->headersWithoutSignature()]);
        $this->post('/events', $this->closingPayload());

        $this->assertResponseCode(401);
        $this->assertSame('new', $this->Reports->get(4)->get('status'));
    }

    /** @return array<string, array<int, string>> */
    public static function invalidSignatureProvider(): array
    {
        return [
            'empty' => [''],
            'algorithm only' => ['sha1'],
            'no algorithm' => ['abcdef0123456789'],
            'unknown algorithm' => ['crc32b=abcdef0123456789'],
            'wrong hash' => ['sha1=0000000000000000000000000000000000000000'],
            'sha256 length with sha1 algorithm' => [
                'sha1=' . str_repeat('a', 64),
            ],
        ];
    }

    #[DataProvider('invalidSignatureProvider')]
    public function testAnInvalidSignatureIsRejected(string $signature): void
    {
        $report = $this->Reports->get(4);
        $report->set('status', 'new');
        $this->Reports->save($report);

        $this->configRequest([
            'headers' => $this->headersWithoutSignature() + ['X-Hub-Signature' => $signature],
        ]);
        $this->post('/events', $this->closingPayload());

        $this->assertResponseCode(401);
        $this->assertSame('new', $this->Reports->get(4)->get('status'));
    }

    /**
     * Sign a payload the way GitHub does.
     */
    protected function sign(string $payload): string
    {
        return 'sha1=' . hash_hmac(
            'sha1',
            $payload,
            (string) Configure::read('GithubWebhookSecret'),
        );
    }

    public function testAValidSignatureIsAccepted(): void
    {
        $report = $this->Reports->get(4);
        $report->set('status', 'forwarded');
        $this->Reports->save($report);

        $payload = $this->closingPayload();
        $this->configRequest([
            'headers' => $this->headersWithoutSignature() + [
                'X-Hub-Signature' => $this->sign($payload),
            ],
        ]);
        $this->post('/events', $payload);

        $this->assertResponseCode(201);
        $this->assertSame('resolved', $this->Reports->get(4)->get('status'));
    }

    /**
     * The signature covers the body: the signature of another payload must not
     * be accepted for this one.
     */
    public function testASignatureOfAnotherPayloadIsRejected(): void
    {
        $this->configRequest([
            'headers' => $this->headersWithoutSignature() + [
                'X-Hub-Signature' => $this->sign('{"action":"opened"}'),
            ],
        ]);
        $this->post('/events', $this->closingPayload());

        $this->assertResponseCode(401);
    }

    /**
     * A 204 response has no content, so it must not declare a Content-Type
     * (RFC 9110, sections 8.3 and 15.3.5).
     */
    public function testAnIgnoredEventHasNoContentType(): void
    {
        $payload = '{"action":"labeled","issue":{"number":4}}';
        $this->configRequest([
            'headers' => $this->headersWithoutSignature() + [
                'X-Hub-Signature' => $this->sign($payload),
            ],
        ]);
        $this->post('/events', $payload);

        $this->assertResponseCode(204);
        $this->assertFalse($this->_response->getHeaderLine('Content-Type') !== '');
        $this->assertSame('', (string) $this->_response->getBody());
    }

    /**
     * The refusals explain themselves in JSON.
     */
    public function testARefusalIsJson(): void
    {
        $this->configRequest([
            'headers' => $this->headersWithoutSignature() + [
                'X-Hub-Signature' => 'sha1=' . str_repeat('0', 40),
            ],
        ]);
        $this->post('/events', $this->closingPayload());

        $this->assertResponseCode(401);
        $this->assertContentType('application/json');
        $this->assertSame(
            ['error' => 'The signature of the payload is missing or does not match.'],
            json_decode((string) $this->_response->getBody(), true),
        );
    }
}
