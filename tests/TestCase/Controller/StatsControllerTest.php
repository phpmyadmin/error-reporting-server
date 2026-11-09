<?php

/**
 * Tests for Stats controller.
 *
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

use App\Test\Fixture\DevelopersFixture;
use App\Test\Fixture\IncidentsFixture;
use App\Test\Fixture\NotificationsFixture;
use App\Test\Fixture\ReportsFixture;
use Cake\Cache\Cache;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

use function array_column;
use function array_keys;
use function array_sum;
use function count;
use function json_encode;
use function urlencode;

/**
 * App\Controller\StatsController Test Case
 */
class StatsControllerTest extends TestCase
{
    use IntegrationTestTrait;

    public function getFixtures(): array
    {
        return [
            NotificationsFixture::class,
            DevelopersFixture::class,
            ReportsFixture::class,
            IncidentsFixture::class,
        ];
    }

    public function setUp(): void
    {
        parent::setUp();

        // Make sure every request starts with a cold cache
        Cache::clear();
    }

    public function tearDown(): void
    {
        parent::tearDown();

        Cache::clear();
    }

    /** @return array<string, array<int, string|null>> */
    public static function filterProvider(): array
    {
        return [
            'no filter' => [null, null],
            'all time' => ['all_time', 'all_time'],
            'last day' => ['day', 'day'],
            'last week' => ['week', 'week'],
            'last month' => ['month', 'month'],
            'last year' => ['year', 'year'],
        ];
    }

    #[DataProvider('filterProvider')]
    public function testStats(string|null $filter, string|null $expectedSelectedFilter): void
    {
        $this->session(['Developer.id' => 1, 'read_only' => false]);

        $url = '/stats';
        if ($filter !== null) {
            $url .= '?filter=' . $filter;
        }

        $this->get($url);

        $this->assertResponseCode(200);
        $this->assertResponseContains('Stats and Graphs');
        $this->assertResponseContains('<option value="day"');
        $this->assertResponseContains('var chartArray =');
        $this->assertResponseContains('var linechart_data =');

        $columns = $this->viewVariable('columns');
        $this->assertSame(
            [
                'browser',
                'pma_version',
                'php_version',
                'locale',
                'server_software',
                'user_os',
                'script_name',
                'configuration_storage',
            ],
            $columns,
        );
        $this->assertSame($expectedSelectedFilter, $this->viewVariable('selected_filter'));

        $filterTimes = $this->viewVariable('filter_times');
        $this->assertSame('All Time', $filterTimes['all_time']['label']);
        $this->assertSame('Last Day', $filterTimes['day']['label']);
        $this->assertSame('Last Week', $filterTimes['week']['label']);
        $this->assertSame('Last Month', $filterTimes['month']['label']);
        $this->assertSame('Last Year', $filterTimes['year']['label']);

        $relatedEntries = $this->viewVariable('related_entries');
        $this->assertSame($columns, array_keys($relatedEntries));
        foreach ($relatedEntries as $entries) {
            $this->assertIsArray($entries);
        }

        $downloadStats = $this->viewVariable('download_stats');
        $this->assertIsArray($downloadStats);
        if ($filter === null || $filter === 'all_time') {
            // The fixture incidents all happened in the same month
            $this->assertCount(1, $downloadStats);
            $this->assertSame(5, $downloadStats[0]['count']);
        } else {
            // The fixture incidents are older than any of the time filters
            $this->assertCount(0, $downloadStats);
        }
    }

    public function testStatsBuildsStatisticsFromScratch(): void
    {
        $this->session(['Developer.id' => 1, 'read_only' => false]);

        $this->assertNull(Cache::read('browser_all_time'));
        $this->assertNull(Cache::read('downloadStats_all_time'));

        $this->get('/stats');
        $this->assertResponseCode(200);

        $this->assertNotFalse(Cache::read('browser_all_time'));
        $this->assertNotFalse(Cache::read('downloadStats_all_time'));

        $relatedEntries = $this->viewVariable('related_entries');
        // The fixtures contain two distinct PHP versions
        $this->assertCount(2, $relatedEntries['php_version']);
        $this->assertSame(5, array_sum(array_column($relatedEntries['php_version'], 'count')));
    }

    /** @return array<string, array<int, string>> */
    public static function unknownFilterProvider(): array
    {
        return [
            'unknown name' => ['doesnotexist'],
            'wrong case' => ['DAY'],
            'trailing space' => ['all_time '],
            'numeric' => ['0'],
            'markup' => ['<script>alert(1)</script>'],
        ];
    }

    #[DataProvider('unknownFilterProvider')]
    public function testAnUnknownFilterFallsBackToAllTime(string $filter): void
    {
        $this->session(['Developer.id' => 1, 'read_only' => false]);

        $this->get('/stats?filter=' . urlencode($filter));

        $this->assertResponseCode(200);
        $this->assertSame('all_time', $this->viewVariable('selected_filter'));
        $this->assertNotFalse(Cache::read('browser_all_time'));
        $this->assertNotFalse(Cache::read('downloadStats_all_time'));
        $this->assertNull(Cache::read('browser_' . $filter));
        $this->assertNull(Cache::read('downloadStats_' . $filter));
    }

    public function testAnArrayFilterFallsBackToAllTime(): void
    {
        $this->session(['Developer.id' => 1, 'read_only' => false]);

        $this->get('/stats?filter%5B%5D=day');

        $this->assertResponseCode(200);
        $this->assertSame('all_time', $this->viewVariable('selected_filter'));
        $this->assertNotFalse(Cache::read('browser_all_time'));
    }

    public function testStatsUsesTheCachedStatistics(): void
    {
        $this->session(['Developer.id' => 1, 'read_only' => false]);

        $cachedBrowserEntries = json_encode([
            ['browser' => 'CachedBrowser', 'count' => 42],
        ]);
        $cachedDownloadStats = json_encode([
            ['date' => 'Thu Aug 29 2013 18:10:01', 'count' => 42],
        ]);
        Cache::write('browser_all_time', $cachedBrowserEntries);
        Cache::write('downloadStats_all_time', $cachedDownloadStats);

        $this->get('/stats');
        $this->assertResponseCode(200);

        $relatedEntries = $this->viewVariable('related_entries');
        $this->assertSame('CachedBrowser', $relatedEntries['browser'][0]['browser']);
        $this->assertSame(42, $relatedEntries['browser'][0]['count']);
        $this->assertSame(42, $this->viewVariable('download_stats')[0]['count']);
    }

    public function testStatsRequiresToBeSignedIn(): void
    {
        $this->get('/stats');

        $this->assertResponseCode(307);
        $this->assertRedirect('/');
        $this->assertFlashMessage('You need to be signed in to do this action (Stats:stats)');
    }

    public function testStatsIsNotAvailableToReadOnlyDevelopers(): void
    {
        $this->session(['Developer.id' => 1, 'read_only' => true]);

        $this->get('/stats');

        $this->assertResponseCode(307);
        $this->assertRedirect('/');
        $this->assertFlashMessage(
            'You need to have commit access on phpmyadmin/phpmyadmin repository on Github.com to do this action (Stats:stats)',
        );
    }

    public function testStatsIsReachableThroughTheFallbackRoute(): void
    {
        $this->session(['Developer.id' => 1, 'read_only' => false]);

        $this->get('/stats/stats');

        $this->assertResponseCode(200);
        $this->assertSame(count($this->viewVariable('columns')), count($this->viewVariable('related_entries')));
    }
}
