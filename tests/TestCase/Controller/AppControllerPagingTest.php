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

use App\Controller\AppController;
use Cake\Http\ServerRequest;
use Cake\TestSuite\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;

/**
 * The paging values of the data tables come from the query string.
 */
class AppControllerPagingTest extends TestCase
{
    /**
     * Read the protected helper the data table actions share.
     *
     * @param array<string, string> $query The query string of the request
     *
     * @return array<int, int> The number of rows, and the offset
     */
    protected function paging(array $query): array
    {
        $controller = new AppController(new ServerRequest(['query' => $query]));
        $method = new ReflectionMethod($controller, 'getDataTablePaging');
        $method->setAccessible(true);

        return $method->invoke($controller);
    }

    /** @return array<string, array{string, int}> */
    public static function lengthProvider(): array
    {
        return [
            'the default of the page' => ['10', 10],
            'the maximum' => ['100', 100],
            'one above the maximum' => ['101', 25],
            'the whole table' => ['100000000', 25],
            'zero' => ['0', 25],
            'negative' => ['-1', 25],
            'not a number' => ['all', 25],
            'missing' => ['', 25],
        ];
    }

    #[DataProvider('lengthProvider')]
    public function testTheLengthIsBounded(string $sent, int $expected): void
    {
        [$limit] = $this->paging(['iDisplayLength' => $sent]);

        $this->assertSame($expected, $limit);
    }

    public function testTheStartIsNotNegative(): void
    {
        [, $offset] = $this->paging(['iDisplayLength' => '10', 'iDisplayStart' => '-50']);

        $this->assertSame(0, $offset);
    }

    public function testTheStartIsKept(): void
    {
        [, $offset] = $this->paging(['iDisplayLength' => '10', 'iDisplayStart' => '250']);

        $this->assertSame(250, $offset);
    }
}
