<?php

namespace App\Test\TestCase;

use App\Report;
use Cake\Utility\Security;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function file_get_contents;
use function json_decode;

class ReportTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        Security::setSalt(
            'Ue%n8*v*QPejgq*v2^k5JSEMXx9Cer*jConpbjp&&vGK89jGRVBnyWaPBhY5s$87',
        );
    }

    public function testReportFromObject(): void
    {
        $contents = file_get_contents(
            __DIR__ . '/../Fixture/report_js.json',
        );
        $obj = json_decode($contents);
        $report1 = Report::fromObject($obj);
        $report2 = Report::fromString($contents);

        $this->assertEquals($report1, $report2);
    }

    public function testUserId(): void
    {
        $contents = file_get_contents(
            __DIR__ . '/../Fixture/report_issue_16853_js.json',
        );
        $report = Report::fromString($contents);

        // The fixture carries no date, so getTimestampUTC() would read the
        // clock and could answer the next second between the two comparisons.
        $report->setTimestamp('2024-02-29T12:34:56+00:00');

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
            $report->getUser(),
        );

        $this->assertSame(
            '99a62784e874c1a5b4625660a94c736bbb8ca46f53d321ab8ce700050e30cb97',
            $report->getUserId(),
        );

        $oldForwardedValue = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null;

        $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.1.1.1';

        $this->assertEquals(
            (object) [
                'ip_address' => '0.0.0.0',
                'id' => '747901a7736cb599fefec3b52bb939a511fb7662a66ba30e6049b128f2114cec',
            ],
            $report->getUser(),
        );
        $_SERVER['HTTP_X_FORWARDED_FOR'] = $oldForwardedValue;
        $this->assertEquals(
            (object) [
                'ip_address' => '0.0.0.0',
                'id' => '99a62784e874c1a5b4625660a94c736bbb8ca46f53d321ab8ce700050e30cb97',
            ],
            $report->getUser(),
        );
        Security::setSalt(
            'N8KB3iKch@xT4nP$WcPoF!9fwxtT7pNY5Hm4*Ld6G^Ux3tQ57iJzPYumu@jXVzue',
        );

        $this->assertEquals(
            (object) [
                'ip_address' => '0.0.0.0',
                'id' => '883a06cb74b037abf70b14ddd6f5518b04c10031648b9af72bc24ddb97fbfd52',
            ],
            $report->getUser(),
        );
    }

    public function testReportRoutingSystem(): void
    {
        $contents = file_get_contents(
            __DIR__ . '/../Fixture/report_issue_16853_js.json',
        );
        $report = Report::fromString($contents);

        // The fixture carries no date, so getTimestampUTC() would read the
        // clock and could answer the next second between the two comparisons.
        $report->setTimestamp('2024-02-29T12:34:56+00:00');

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
            $user,
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
            __DIR__ . '/../Fixture/report_js.json',
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
            'version_major' => '5',
            'version_series' => '5.2',
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
            $user,
        );
        $this->assertFalse($report->isMultiReports());
        $this->assertTrue($report->hasUserFeedback());
        $this->assertSame('<script>test steps', $report->getUserFeedback());
        $this->assertEquals([
            [
                'sentry.interfaces.Message' => $message,
                'release' => '5.2.4',
                'dist' => '5.2.4',
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
            'release' => '5.2.4',
            'dist' => '5.2.4',
            'platform' => 'javascript',
            'timestamp' => $report->getTimestampUTC(),
            'tags' => $tags,
            'contexts' => $contexts,
            'extra' => $extras,
            'user' => $user,
            'environment' => 'production',
        ], $toJson);
    }

    public function testSetTimestamp(): void
    {
        $report = Report::fromString(
            file_get_contents(__DIR__ . '/../Fixture/report_js.json'),
        );

        $report->setTimestamp('2024-02-29T12:34:56+00:00');

        $this->assertSame('2024-02-29T12:34:56+00:00', $report->getTimestampUTC());
        $this->assertSame(
            '2024-02-29T12:34:56+00:00',
            $report->toJson()['timestamp'],
        );
    }

    public function testGetTimestampUTCWithoutTimestamp(): void
    {
        $report = new Report();

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/',
            $report->getTimestampUTC(),
        );
    }

    public function testGetEventIdIsCached(): void
    {
        $report = new Report();

        $eventId = $report->getEventId();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $eventId);
        $this->assertSame($eventId, $report->getEventId());
        $this->assertNotSame($eventId, (new Report())->getEventId());
    }

    public function testPhpPayloadFromObject(): void
    {
        $contents = file_get_contents(__DIR__ . '/../Fixture/report_php.json');
        $report = Report::fromObject(json_decode($contents));

        $this->assertEquals(Report::fromString($contents), $report);

        $this->assertSame('5.2.4', $report->pma_version);
        $this->assertSame('CHROME', $report->browser_name);
        $this->assertSame('27.0.1453.93', $report->browser_version);
        $this->assertSame('Linux', $report->user_os);
        $this->assertSame('php', $report->exception_type);
        $this->assertCount(3, $report->errors);
        $this->assertSame('Notice', $report->errors[0]->type);
        $this->assertSame('./libraries/Config.class.php', $report->errors[0]->file);
        $this->assertTrue($report->isMultiReports());
        $this->assertSame('5.2.4', $report->getPmaVersion());
        $this->assertSame('5', $report->getTags()->version_major);
        $this->assertSame('5.2', $report->getTags()->version_series);
        $this->assertEquals((object) ['name' => 'Linux'], $report->getContexts()->os);
        $this->assertSame(
            '079f075fb3f0d366576122b0aacab77b977d58834677f930d6b571f7ddc2f518',
            $report->getUserId(),
        );
        $this->assertEquals((object) [
            'ip_address' => '0.0.0.0',
            'id' => '079f075fb3f0d366576122b0aacab77b977d58834677f930d6b571f7ddc2f518',
        ], $report->getUser());
        $this->assertFalse($report->hasUserFeedback());
        $this->assertSame('', $report->getUserFeedback());
        $this->assertEquals((object) ['message' => ''], $report->getUserMessage());
    }

    public function testNullUserAgentPayload(): void
    {
        $report = Report::fromString(
            file_get_contents(__DIR__ . '/../Fixture/report_ua_null_js.json'),
        );

        $tags = $report->getTags();
        $this->assertSame('5', $tags->version_major);
        $this->assertSame('5.2', $tags->version_series);
        $this->assertNull($tags->user_agent_string);
        $this->assertTrue($tags->configuration_storage);
        $this->assertSame('js', $tags->exception_type);
        $this->assertEquals((object) [
            'os' => (object) ['name' => 'Other'],
            'browser' => (object) [
                'name' => 'OTHER',
                'version' => 0,
            ],
        ], $report->getContexts());
        $this->assertSame(
            'c7d0ea6886cc827a9ade6a5e4e6844d73e86c197f98bfa7970f8b10f460e6047',
            $report->getUserId(),
        );
        $this->assertFalse($report->isMultiReports());

        $reports = $report->getReports();

        $this->assertCount(1, $reports);
        $this->assertSame('javascript', $reports[0]['platform']);
        $this->assertSame('development', $reports[0]['environment']);
        $this->assertSame('index.php', $reports[0]['culprit']);
        $this->assertArrayNotHasKey('transaction', $reports[0]);
        $this->assertSame('', $reports[0]['message']);
        $this->assertEquals((object) [
            'type' => '',
            'value' => '',
            'stacktrace' => (object) ['frames' => []],
        ], $reports[0]['exception']['values'][0]);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $reports[0]['event_id']);
    }

    public function testGetRouteWithoutException(): void
    {
        $report = Report::fromObject((object) ['script_name' => 'index.php']);

        $this->assertNull($report->getRoute());
    }

    public function testGetRouteWithoutExceptionUri(): void
    {
        $report = Report::fromObject((object) [
            'exception' => (object) ['message' => 'boom'],
        ]);

        $this->assertNull($report->getRoute());
    }

    #[DataProvider('routeProvider')]
    public function testGetRoute(string|null $uri, string|null $expected): void
    {
        $report = Report::fromObject((object) [
            'exception' => (object) ['uri' => $uri],
        ]);

        $this->assertSame($expected, $report->getRoute());
    }

    /** @return array<string, array{string|null, string|null}> */
    public static function routeProvider(): array
    {
        return [
            'null uri' => [null, null],
            'no query' => ['index.php', null],
            'empty query' => ['index.php?', null],
            'query without route' => ['index.php?db=test', null],
            'malformed url' => ['http://', null],
            'route' => ['index.php?route=/sql&db=test', '/sql'],
            'encoded route' => ['index.php?route=%2Fserver%2Fdatabases', '/server/databases'],
        ];
    }

    #[DataProvider('typeToLevelProvider')]
    public function testTypeToLevel(string $type, string $expected): void
    {
        $report = new Report();

        $this->assertSame($expected, $report->typeToLevel($type));
    }

    /** @return array<string, array{string, string}> */
    public static function typeToLevelProvider(): array
    {
        return [
            'Internal error' => ['Internal error', 'error'],
            'Parsing Error' => ['Parsing Error', 'error'],
            'Error' => ['Error', 'error'],
            'Core Error' => ['Core Error', 'error'],
            'User Error' => ['User Error', 'info'],
            'User Warning' => ['User Warning', 'info'],
            'User Notice' => ['User Notice', 'info'],
            'Warning' => ['Warning', 'warning'],
            'Runtime Notice' => ['Runtime Notice', 'warning'],
            'Deprecation Notice' => ['Deprecation Notice', 'warning'],
            'Notice' => ['Notice', 'warning'],
            'Compile Warning' => ['Compile Warning', 'warning'],
            'Catchable Fatal Error' => ['Catchable Fatal Error', 'fatal'],
            'unknown type' => ['Something else', 'error'],
        ];
    }

    public function testGetMultiDataToSendFromPhpPayload(): void
    {
        $report = Report::fromString(
            file_get_contents(__DIR__ . '/../Fixture/report_php.json'),
        );

        $expected = [
            [
                'platform' => 'php',
                'level' => 'warning',
                'exception' => [
                    'values' => [
                        (object) [
                            'type' => 'Notice',
                            'value' => 'Undefined variable: haha',
                            'stacktrace' => (object) [
                                'frames' => [
                                    [
                                        'platform' => 'php',
                                        'function' => 'getFontsizeForm',
                                        'lineno' => 228,
                                        'filename' => './libraries/Config.class.php',
                                        'package' => 'PMA_Config',
                                        'symbol_addr' => ':: ',
                                        'vars' => (object) [],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'message' => 'Undefined variable: haha',
                'culprit' => './libraries/Config.class.php',
            ],
            [
                'platform' => 'php',
                'level' => 'warning',
                'exception' => [
                    'values' => [
                        (object) [
                            'type' => 'Notice',
                            'value' => 'Undefined variable: hihi',
                            'stacktrace' => (object) [
                                'frames' => [
                                    [
                                        'platform' => 'php',
                                        'function' => 'showPHPDocu',
                                        'lineno' => 322,
                                        'filename' => './libraries/Util.class.php',
                                        'package' => 'PMA_Util',
                                        'symbol_addr' => ':: ',
                                        'vars' => (object) ['book.mysqli.php'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'message' => 'Undefined variable: hihi',
                'culprit' => './libraries/Util.class.php',
            ],
            [
                'platform' => 'php',
                'level' => 'warning',
                'exception' => [
                    'values' => [
                        (object) [
                            'type' => 'Notice',
                            'value' => 'Undefined variable: hehe',
                            'stacktrace' => (object) ['frames' => []],
                        ],
                    ],
                ],
                'message' => 'Undefined variable: hehe',
                'culprit' => './index.php',
            ],
        ];

        $this->assertEquals($expected, $report->getMultiDataToSend());
    }

    public function testGetMultiDataToSendWithPartialErrors(): void
    {
        $report = Report::fromObject((object) [
            'exception_type' => 'php',
            'pma_version' => '5.2.4',
            'errors' => [
                (object) [
                    'type' => 'Catchable Fatal Error',
                    'msg' => 'Cannot use &quot;foo&quot; as &#039;bar&#039;',
                    'file' => './index.php',
                    'stackTrace' => [
                        (object) [],
                    ],
                ],
                (object) [
                    'type' => 'User Notice',
                    'msg' => 'Something',
                    'file' => './other.php',
                    'stackTrace' => [],
                ],
            ],
        ]);

        $data = $report->getMultiDataToSend();

        $this->assertCount(2, $data);

        $this->assertSame('fatal', $data[0]['level']);
        $this->assertSame('Cannot use "foo" as \'bar\'', $data[0]['message']);
        $this->assertSame('Catchable Fatal Error', $data[0]['exception']['values'][0]->type);
        $this->assertSame(
            'Cannot use "foo" as \'bar\'',
            $data[0]['exception']['values'][0]->value,
        );
        $this->assertSame(
            [
                'platform' => 'php',
                'function' => '',
                'lineno' => 0,
                'filename' => './index.php',
            ],
            $data[0]['exception']['values'][0]->stacktrace->frames[0],
        );

        $this->assertSame('info', $data[1]['level']);
        $this->assertSame([], $data[1]['exception']['values'][0]->stacktrace->frames);
    }

    public function testGetReportsForPhpPayload(): void
    {
        $report = Report::fromString(
            file_get_contents(__DIR__ . '/../Fixture/report_php.json'),
        );
        $report->setTimestamp('2024-01-02T03:04:05+00:00');

        $this->assertTrue($report->isMultiReports());

        $multiData = $report->getMultiDataToSend();
        $reports = $report->getReports();

        $this->assertCount(3, $multiData);
        $this->assertCount(3, $reports);

        $eventId = $report->getEventId();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $eventId);

        foreach ($reports as $index => $singleReport) {
            $this->assertSame($eventId, $singleReport['event_id']);
            $this->assertSame('2024-01-02T03:04:05+00:00', $singleReport['timestamp']);
            $this->assertSame('php', $singleReport['platform']);
            $this->assertSame($multiData[$index]['level'], $singleReport['level']);
            $this->assertSame($multiData[$index]['message'], $singleReport['message']);
            $this->assertSame($multiData[$index]['culprit'], $singleReport['culprit']);
            $this->assertEquals($multiData[$index]['exception'], $singleReport['exception']);
            $this->assertEquals($report->getTags(), $singleReport['tags']);
            $this->assertEquals($report->getContexts(), $singleReport['contexts']);
            $this->assertEquals($report->getUser(), $singleReport['user']);
            $this->assertSame('production', $singleReport['environment']);
            $this->assertSame('5.2.4', $singleReport['release']);
            $this->assertSame('5.2.4', $singleReport['dist']);
            $this->assertEquals((object) [], $singleReport['extra']);
            $this->assertArrayNotHasKey('transaction', $singleReport);
        }

        $this->assertSame('Undefined variable: haha', $reports[0]['message']);
        $this->assertSame('Undefined variable: hihi', $reports[1]['message']);
        $this->assertSame('Undefined variable: hehe', $reports[2]['message']);
        $this->assertSame('./libraries/Config.class.php', $reports[0]['culprit']);
        $this->assertSame('./libraries/Util.class.php', $reports[1]['culprit']);
        $this->assertSame('./index.php', $reports[2]['culprit']);
    }

    public function testGetReportsWithoutErrors(): void
    {
        $report = Report::fromObject((object) [
            'exception_type' => 'php',
            'errors' => [],
        ]);

        $this->assertTrue($report->isMultiReports());
        $this->assertSame([], $report->getMultiDataToSend());
        $this->assertSame([], $report->getReports());
    }
}
