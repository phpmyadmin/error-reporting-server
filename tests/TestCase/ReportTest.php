<?php

namespace App\Test;

use App\Report;
use Cake\Utility\Security;
use PHPUnit\Framework\TestCase;

use function file_get_contents;
use function json_decode;

class ReportTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        Security::setSalt(
            'Ue%n8*v*QPejgq*v2^k5JSEMXx9Cer*jConpbjp&&vGK89jGRVBnyWaPBhY5s$87'
        );
    }

    public function testReportFromObject(): void
    {
        $contents = file_get_contents(
            __DIR__ . '/../Fixture/report_js.json'
        );
        $obj = json_decode($contents);
        $report1 = Report::fromObject($obj);
        $report2 = Report::fromString($contents);

        $this->assertEquals($report1, $report2);
    }

    public function testUserId(): void
    {
        $contents = file_get_contents(
            __DIR__ . '/../Fixture/report_issue_16853_js.json'
        );
        $report = Report::fromString($contents);

        $tags = $report->getTags();

        $this->assertEquals((object) [
            'server_software' => 'nginx/1.14.2',
            'user_agent_string' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:90.0) Gecko/20100101 Firefox/90.0',
            'locale' => 'en',
            'configuration_storage' => false,
            'php_version' => '7.4.14',
            'exception_type' => 'js',
            'version_major' => '5',
            'version_series' => '5.2',
        ], $tags);
        $this->assertEquals(
            (object) [
                'ip_address' => '0.0.0.0',
                'id' => '99a62784e874c1a5b4625660a94c736bbb8ca46f53d321ab8ce700050e30cb97',
            ],
            $report->getUser()
        );

        $this->assertSame(
            '99a62784e874c1a5b4625660a94c736bbb8ca46f53d321ab8ce700050e30cb97',
            $report->getUserId()
        );

        $oldForwardedValue = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null;

        $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.1.1.1';

        $this->assertEquals(
            (object) [
                'ip_address' => '0.0.0.0',
                'id' => '747901a7736cb599fefec3b52bb939a511fb7662a66ba30e6049b128f2114cec',
            ],
            $report->getUser()
        );
        $_SERVER['HTTP_X_FORWARDED_FOR'] = $oldForwardedValue;
        $this->assertEquals(
            (object) [
                'ip_address' => '0.0.0.0',
                'id' => '99a62784e874c1a5b4625660a94c736bbb8ca46f53d321ab8ce700050e30cb97',
            ],
            $report->getUser()
        );
        Security::setSalt(
            'N8KB3iKch@xT4nP$WcPoF!9fwxtT7pNY5Hm4*Ld6G^Ux3tQ57iJzPYumu@jXVzue'
        );

        $this->assertEquals(
            (object) [
                'ip_address' => '0.0.0.0',
                'id' => '883a06cb74b037abf70b14ddd6f5518b04c10031648b9af72bc24ddb97fbfd52',
            ],
            $report->getUser()
        );
    }

    public function testReportRoutingSystem(): void
    {
        $contents = file_get_contents(
            __DIR__ . '/../Fixture/report_issue_16853_js.json'
        );
        $report = Report::fromString($contents);

        $tags = $report->getTags();
        $contexts = $report->getContexts();
        $extras = $report->getExtras();
        $reports = $report->getReports();
        $user = $report->getUser();
        $message = $report->getUserMessage();
        $toJson = $report->toJson();

        $this->assertEquals((object) [
            'server_software' => 'nginx/1.14.2',
            'user_agent_string' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:90.0) Gecko/20100101 Firefox/90.0',
            'locale' => 'en',
            'configuration_storage' => false,
            'php_version' => '7.4.14',
            'exception_type' => 'js',
            'version_major' => '5',
            'version_series' => '5.2',
        ], $tags);
        $this->assertEquals((object) [
            'os' => (object) ['name' => 'Win'],
            'browser' => (object) [
                'name' => 'FIREFOX',
                'version' => '90.0',
            ],
        ], $contexts);
        $this->assertEquals((object) [], $extras);
        $this->assertEquals((object) ['message' => ''], $message);
        $this->assertEquals(
            (object) [
                'ip_address' => '0.0.0.0',
                'id' => '99a62784e874c1a5b4625660a94c736bbb8ca46f53d321ab8ce700050e30cb97',
            ],
            $user
        );
        $this->assertFalse($report->isMultiReports());
        $this->assertFalse($report->hasUserFeedback());
        $this->assertEquals([
            [
                'sentry.interfaces.Message' => $message,
                'release' => '5.2.0-dev',
                'dist' => '5.2.0-dev',
                'platform' => 'javascript',
                'environment' => 'development',
                'transaction' => '/database/designer',
                'timestamp' => $report->getTimestampUTC(),
                'tags' => $tags,
                'contexts' => $contexts,
                'extra' => $extras,
                'user' => $user,
                'exception' => [
                    'values' => [
                        (object) [
                            'type' => 'TypeError',
                            'value' => 'can\'t access property "transaction", db is null',
                            'stacktrace' => (object) [
                                'frames' => $reports[0]['exception']['values'][0]->stacktrace->frames,
                            ],
                        ],
                    ],
                ],
                'message' => 'can\'t access property "transaction", db is null',
                'culprit' => 'index.php',
                'event_id' => $report->getEventId(),
            ],
        ], $reports);
        $this->assertEquals([
            'sentry.interfaces.Message' => $message,
            'release' => '5.2.0-dev',
            'dist' => '5.2.0-dev',
            'platform' => 'javascript',
            'environment' => 'development',
            'transaction' => '/database/designer',
            'timestamp' => $report->getTimestampUTC(),
            'tags' => $tags,
            'contexts' => $contexts,
            'extra' => $extras,
            'user' => $user,
        ], $toJson);
    }

    public function testReportFromString(): void
    {
        $contents = file_get_contents(
            __DIR__ . '/../Fixture/report_js.json'
        );
        $report = Report::fromString($contents);

        $tags = $report->getTags();
        $contexts = $report->getContexts();
        $extras = $report->getExtras();
        $reports = $report->getReports();
        $user = $report->getUser();
        $message = $report->getUserMessage();
        $toJson = $report->toJson();

        $this->assertEquals((object) [
            'server_software' => 'NginX/1.17 (Unix) mod_ssl/2.2.25 OpenSSL/1.0.1e DAV/2 PHP/5.4.17',
            'user_agent_string' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/27.0.1453.110 Safari/537.36',
            'locale' => 'en',
            'configuration_storage' => true,
            'php_version' => '5.2.2',
            'exception_type' => null,
            'version_major' => '4',
            'version_series' => '4.5',
        ], $tags);
        $this->assertEquals((object) [
            'os' => (object) ['name' => 'Windows'],
            'browser' => (object) [
                'name' => 'FIREFOX',
                'version' => '17.5',
            ],
        ], $contexts);
        $this->assertEquals((object) [], $extras);
        $this->assertEquals((object) ['message' => ''], $message);
        $this->assertEquals(
            (object) [
                'ip_address' => '0.0.0.0',
                'id' => '90244836bc888d4b4bb8449010dba862ebc5a8905a79da8269b4e76d08831226',
            ],
            $user
        );
        $this->assertFalse($report->isMultiReports());
        $this->assertTrue($report->hasUserFeedback());
        $this->assertSame('<script>test steps', $report->getUserFeedback());
        $this->assertEquals([
            [
                'sentry.interfaces.Message' => $message,
                'release' => '4.5.4.1',
                'dist' => '4.5.4.1deb2ubuntu2',
                'platform' => 'javascript',
                'environment' => 'production',
                'timestamp' => $report->getTimestampUTC(),
                'tags' => $tags,
                'contexts' => $contexts,
                'extra' => $extras,
                'user' => $user,
                'exception' => [
                    'values' => [
                        (object) [
                            'type' => 'ReferenceError',
                            'value' => 'a is not defined',
                            'stacktrace' => (object) [
                                'frames' => [
                                    [
                                        'platform' => 'javascript',
                                        'function' => 'PMA_exception',
                                        'lineno' => 312,
                                        'colno' => 5,
                                        'abs_path' => '',
                                        'filename' => '',
                                    ],
                                    [
                                        'platform' => 'javascript',
                                        'function' => 'new_func',
                                        'lineno' => 257,
                                        'colno' => 33,
                                        'abs_path' => '',
                                        'filename' => '',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'message' => 'a is not defined',
                'culprit' => 'tbl_relation.php',
                'event_id' => $report->getEventId(),
            ],
        ], $reports);
        $this->assertEquals([
            'sentry.interfaces.Message' => $message,
            'release' => '4.5.4.1',
            'dist' => '4.5.4.1deb2ubuntu2',
            'platform' => 'javascript',
            'timestamp' => $report->getTimestampUTC(),
            'tags' => $tags,
            'contexts' => $contexts,
            'extra' => $extras,
            'user' => $user,
            'environment' => 'production',
        ], $toJson);
    }
}
