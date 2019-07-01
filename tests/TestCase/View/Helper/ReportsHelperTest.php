<?php
/* vim: set expandtab sw=4 ts=4 sts=4: */

namespace App\Test\TestCase\View\Helper;

use App\View\Helper\ReportsHelper;
use Cake\TestSuite\TestCase;
use Cake\View\View;

class ReportsHelperTest extends TestCase
{
    public function setUp()
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
    public function providerForTestLinkToReport()
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
                    'exception_type' => 'js'
                ],
                '<a href=/reports/view/116273>#116273</a>',
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
                    'exception_type' => 'php'
                ],
                '<a href=/reports/view/1879>#1879</a>',
            ],
        ];
    }

    /**
     * @dataProvider providerForTestLinkToReport
     * @param array $report   The report
     * @param array $expected The expected
     * @return void
     */
    public function testLinkToReport($report, $expected)
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
    public function providerForTestCreateReportsLinks()
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
                        'exception_type' => 'js'
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
                        'exception_type' => 'php'
                    ],
                ],
                '<a href=/reports/view/116273>#116273</a>, '
                    . '<a href=/reports/view/1879>#1879</a>',
            ],
        ];
    }


    /**
     * @dataProvider providerForTestCreateReportsLinks
     * @param array $reports  The reports
     * @param array $expected The expected reports
     * @return void
     */
    public function testCreateReportsLinks($reports, $expected)
    {
        $links_str = $this->Reports->createReportsLinks($reports);

        $this->assertEquals(
            $expected,
            $links_str
        );
    }
}
