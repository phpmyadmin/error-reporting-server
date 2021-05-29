<?php

namespace App\Test;

use App\Report;
use PHPUnit\Framework\TestCase;

use function file_get_contents;
use function json_decode;

class ReportTest extends TestCase
{
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
        $this->assertEquals((object) ['ip_address' => '0.0.0.0'], $user);
        $this->assertFalse($report->isMultiReports());
        $this->assertTrue($report->hasUserFeedback());
        $this->assertSame('<script>test steps', $report->getUserFeedback());
        $this->assertEquals([
            [
                'sentry.interfaces.Message' => $message,
                'release' => '4.5.4.1deb2ubuntu2',
                'platform' => 'javascript',
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
            'release' => '4.5.4.1deb2ubuntu2',
            'platform' => 'javascript',
            'timestamp' => $report->getTimestampUTC(),
            'tags' => $tags,
            'contexts' => $contexts,
            'extra' => $extras,
            'user' => $user,
        ], $toJson);
    }
}
