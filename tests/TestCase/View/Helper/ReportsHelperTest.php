<?php

namespace App\Test\TestCase\View\Helper;

use App\View\Helper\ReportsHelper;
use Cake\TestSuite\TestCase;
use Cake\View\View;
use PHPUnit\Framework\Attributes\DataProvider;

class ReportsHelperTest extends TestCase
{
    protected ReportsHelper $Reports;

    public function setUp(): void
    {
        parent::setUp();
        $View = new View();
        $this->Reports = new ReportsHelper($View);
    }

    /**
     * Provider for testLinkToReport
     *
     * @return array array data for testLinkToReport
     */
    public static function providerForTestLinkToReport(): array
    {
        return [
            [
                [
                    'id' => 116273,
                    'error_message' => 'TypeError: url is undefined',
                    'error_name' => null,
                    'pma_version' => '4.7.1',
                    'status' => 'new',
                    'location' => 'index.js',
                    'linenumber' => 154,
                    'sourceforge_bug_id' => null,
                    'related_to' => 12567,
                    'exception_type' => 'js',
                ],
                '<a href="/reports/view/116273">#116273</a>',
            ],
            [
                [
                    'id' => 1879,
                    'error_message' => 'TypeError: url is undefined',
                    'error_name' => null,
                    'pma_version' => '4.1.6',
                    'status' => 'new',
                    'location' => 'common.js',
                    'linenumber' => 154,
                    'sourceforge_bug_id' => null,
                    'related_to' => null,
                    'exception_type' => 'php',
                ],
                '<a href="/reports/view/1879">#1879</a>',
            ],
        ];
    }

    /**
     * @param array  $report   The report
     * @param string $expected The expected
     */
    #[DataProvider('providerForTestLinkToReport')]
    public function testLinkToReport(array $report, string $expected): void
    {
        $link = $this->Reports->linkToReport($report);

        $this->assertEquals(
            $expected,
            $link
        );
    }

    /**
     * Provider for testCreateReportsLinks
     *
     * @return array array data to testCreateReportsLinks
     */
    public static function providerForTestCreateReportsLinks(): array
    {
        return [
            [
                [
                    [
                        'id' => 116273,
                        'error_message' => 'TypeError: url is undefined',
                        'error_name' => null,
                        'pma_version' => '4.7.1',
                        'status' => 'new',
                        'location' => 'index.js',
                        'linenumber' => 154,
                        'sourceforge_bug_id' => null,
                        'related_to' => 12567,
                        'exception_type' => 'js',
                    ],
                    [
                        'id' => 1879,
                        'error_message' => 'TypeError: url is undefined',
                        'error_name' => null,
                        'pma_version' => '4.1.6',
                        'status' => 'new',
                        'location' => 'common.js',
                        'linenumber' => 154,
                        'sourceforge_bug_id' => null,
                        'related_to' => null,
                        'exception_type' => 'php',
                    ],
                ],
                '<a href="/reports/view/116273">#116273</a>, '
                    . '<a href="/reports/view/1879">#1879</a>',
            ],
        ];
    }

    /**
     * @param array  $reports  The reports
     * @param string $expected The expected reports
     */
    #[DataProvider('providerForTestCreateReportsLinks')]
    public function testCreateReportsLinks(array $reports, string $expected): void
    {
        $links_str = $this->Reports->createReportsLinks($reports);

        $this->assertEquals(
            $expected,
            $links_str
        );
    }
}
