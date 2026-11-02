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
use Cake\Log\Engine\ArrayLog;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

use function hash_hmac;
use function implode;
use function is_array;
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

    private ArrayLog $logCapture;

    private mixed $originalWebhookSecret;

    private mixed $originalErrorLogConfig;

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
        $this->originalWebhookSecret = Configure::read('GithubWebhookSecret');
        $this->originalErrorLogConfig = Log::getConfig('error');

        Log::drop('error');
        Log::setConfig('error', ['className' => ArrayLog::class]);
        $errorLog = Log::engine('error');
        $this->assertInstanceOf(ArrayLog::class, $errorLog);
        $this->logCapture = $errorLog;
        $this->logCapture->clear();
    }

    public function tearDown(): void
    {
        Configure::write('GithubWebhookSecret', $this->originalWebhookSecret);

        Log::drop('error');
        if (is_array($this->originalErrorLogConfig)) {
            Log::setConfig('error', $this->originalErrorLogConfig);
        }

        parent::tearDown();
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
     * Sign a payload the way GitHub does, with the given algorithm.
     */
    protected function sign(string $payload, string $algo = 'sha256'): string
    {
        return $algo . '=' . hash_hmac(
            $algo,
            $payload,
            (string) Configure::read('GithubWebhookSecret'),
        );
    }

    /**
     * Send the closing payload with the given signature headers.
     *
     * @param array<string, string> $signatureHeaders Signature headers to add
     */
    protected function postSignedPayload(array $signatureHeaders): void
    {
        $this->configRequest([
            'headers' => $this->headersWithoutSignature() + $signatureHeaders,
        ]);
        $this->post('/events', $this->closingPayload());
    }

    protected function resetReportStatus(): void
    {
        $report = $this->Reports->get(4);
        $report->set('status', 'new');
        $this->Reports->save($report);
    }

    /**
     * Without a signature header the received hash is an empty string, which
     * used to be compared with an empty expected hash.
     */
    public function testAMissingSignatureIsRejected(): void
    {
        $this->resetReportStatus();

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
            'sha256 on the legacy header' => [
                'sha256=' . str_repeat('a', 64),
            ],
        ];
    }

    #[DataProvider('invalidSignatureProvider')]
    public function testAnInvalidLegacySignatureIsRejected(string $signature): void
    {
        $this->resetReportStatus();

        $this->postSignedPayload(['X-Hub-Signature' => $signature]);

        $this->assertResponseCode(401);
        $this->assertSame('new', $this->Reports->get(4)->get('status'));
    }

    /** @return array<string, array<int, string>> */
    public static function nonSha256AlgorithmProvider(): array
    {
        return [
            'md5' => ['md5'],
            'sha1' => ['sha1'],
            'sha384' => ['sha384'],
            'sha512' => ['sha512'],
        ];
    }

    /** @return array<string, array<int, string>> */
    public static function nonSha1AlgorithmProvider(): array
    {
        return [
            'md5' => ['md5'],
            'sha384' => ['sha384'],
            'sha512' => ['sha512'],
        ];
    }

    /**
     * A correctly signed delivery that names another algorithm than sha1 in
     * the legacy header. The old code accepted it, because it read the
     * algorithm from the header; sha1 stays the only fallback.
     */
    #[DataProvider('nonSha1AlgorithmProvider')]
    public function testAValidSignatureOfAnotherAlgorithmIsRejected(string $algo): void
    {
        $this->resetReportStatus();

        $this->postSignedPayload([
            'X-Hub-Signature' => $this->sign($this->closingPayload(), $algo),
        ]);

        $this->assertResponseCode(401);
        $this->assertSame('new', $this->Reports->get(4)->get('status'));
    }

    /**
     * The client used to choose the algorithm: every algorithm of
     * hash_hmac_algos(), md5 included, was accepted. Only sha256 is now.
     */
    #[DataProvider('nonSha256AlgorithmProvider')]
    public function testOnlySha256IsAcceptedOnTheModernHeader(string $algo): void
    {
        $this->resetReportStatus();

        $this->postSignedPayload([
            'X-Hub-Signature-256' => $this->sign($this->closingPayload(), $algo),
        ]);

        $this->assertResponseCode(401);
        $this->assertSame('new', $this->Reports->get(4)->get('status'));
    }

    /** @return array<string, array<int, string>> */
    public static function malformedModernSignatureProvider(): array
    {
        return [
            'empty' => [''],
            'empty hash' => ['sha256='],
            'no prefix' => [str_repeat('a', 64)],
            'unknown algorithm' => ['crc32b=abcdef0123456789'],
            'wrong hash' => ['sha256=' . str_repeat('0', 64)],
            'wrong length' => ['sha256=' . str_repeat('a', 63)],
        ];
    }

    #[DataProvider('malformedModernSignatureProvider')]
    public function testAMalformedModernSignatureIsRejected(string $signature): void
    {
        $this->resetReportStatus();

        $this->postSignedPayload(['X-Hub-Signature-256' => $signature]);

        $this->assertResponseCode(401);
        $this->assertSame('new', $this->Reports->get(4)->get('status'));
    }

    public function testAValidSha256SignatureIsAccepted(): void
    {
        $report = $this->Reports->get(4);
        $report->set('status', 'forwarded');
        $this->Reports->save($report);

        $this->postSignedPayload([
            'X-Hub-Signature-256' => $this->sign($this->closingPayload()),
        ]);

        $this->assertResponseCode(201);
        $this->assertSame('resolved', $this->Reports->get(4)->get('status'));
    }

    /**
     * Documents that already sign with sha1 on the legacy header keep working.
     */
    public function testALegacySha1SignatureIsStillAccepted(): void
    {
        $report = $this->Reports->get(4);
        $report->set('status', 'forwarded');
        $this->Reports->save($report);

        $this->postSignedPayload([
            'X-Hub-Signature' => $this->sign($this->closingPayload(), 'sha1'),
        ]);

        $this->assertResponseCode(201);
        $this->assertSame('resolved', $this->Reports->get(4)->get('status'));
    }

    /**
     * The signature covers the body: the signature of another payload must not
     * be accepted for this one.
     */
    public function testASignatureOfAnotherPayloadIsRejected(): void
    {
        $this->postSignedPayload([
            'X-Hub-Signature-256' => $this->sign('{"action":"opened"}'),
        ]);

        $this->assertResponseCode(401);
    }

    /**
     * An empty secret lets anyone compute the expected HMAC. Every webhook must
     * be refused and the misconfiguration logged.
     */
    public function testAnEmptySecretRefusesEveryWebhook(): void
    {
        Configure::write('GithubWebhookSecret', '');
        $this->resetReportStatus();

        $payload = $this->closingPayload();
        $this->postSignedPayload([
            'X-Hub-Signature-256' => 'sha256=' . hash_hmac('sha256', $payload, ''),
        ]);
        $this->assertResponseCode(401);
        $this->assertSame('new', $this->Reports->get(4)->get('status'));

        $this->configRequest([
            'headers' => [
                'X-GitHub-Event' => 'ping',
                'X-Hub-Signature-256' => 'sha256=' . hash_hmac('sha256', '', ''),
            ] + $this->headersWithoutSignature(),
        ]);
        $this->post('/events');
        $this->assertResponseCode(401);

        $this->assertStringContainsString(
            'GithubWebhookSecret',
            implode("\n", $this->logCapture->read()),
        );
    }

    /**
     * The log is readable by more people than the secret: it must never hold
     * either the expected or the received hash.
     */
    public function testTheLogsNeverContainTheHashes(): void
    {
        $payload = $this->closingPayload();
        $expectedHash = hash_hmac(
            'sha256',
            $payload,
            (string) Configure::read('GithubWebhookSecret'),
        );
        $receivedHash = str_repeat('f', 64);

        $this->postSignedPayload(['X-Hub-Signature-256' => 'sha256=' . $receivedHash]);

        $this->assertResponseCode(401);

        $logs = implode("\n", $this->logCapture->read());
        $this->assertStringContainsStringIgnoringCase('signature', $logs);
        $this->assertStringNotContainsString($expectedHash, $logs);
        $this->assertStringNotContainsString($receivedHash, $logs);
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
                'X-Hub-Signature-256' => $this->sign($payload),
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
        $this->postSignedPayload([
            'X-Hub-Signature-256' => 'sha256=' . str_repeat('0', 64),
        ]);

        $this->assertResponseCode(401);
        $this->assertContentType('application/json');
        $this->assertSame(
            ['error' => 'The signature of the payload is missing or does not match.'],
            json_decode((string) $this->_response->getBody(), true),
        );
    }
}
