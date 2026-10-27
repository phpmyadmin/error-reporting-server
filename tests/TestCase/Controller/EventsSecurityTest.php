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
use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

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

    public function testAValidSignatureIsAccepted(): void
    {
        $report = $this->Reports->get(4);
        $report->set('status', 'forwarded');
        $this->Reports->save($report);

        $this->configRequest([
            'headers' => $this->headersWithoutSignature() + [
                'X-Hub-Signature' => 'sha1=ddcf5dadbbb716e43da25344989dde547c6c3a03',
            ],
        ]);
        $this->post('/events', $this->closingPayload());

        $this->assertResponseCode(201);
        $this->assertSame('resolved', $this->Reports->get(4)->get('status'));
    }
}
