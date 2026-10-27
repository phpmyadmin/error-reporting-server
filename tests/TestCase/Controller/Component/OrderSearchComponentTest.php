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

namespace App\Test\TestCase\Controller\Component;

use App\Controller\Component\OrderSearchComponent;
use Cake\Controller\ComponentRegistry;
use Cake\Http\ServerRequest;
use Cake\TestSuite\TestCase;

use function var_export;

/**
 * Tests for the order and search conditions of the data tables.
 */
class OrderSearchComponentTest extends TestCase
{
    protected OrderSearchComponent $OrderSearch;

    /** @var string[] */
    protected array $columns = [
        'id' => 'id',
        'error_name' => 'error_name',
        'error_message' => 'error_message',
    ];

    public function setUp(): void
    {
        parent::setUp();

        $this->OrderSearch = new OrderSearchComponent(new ComponentRegistry());
    }

    /**
     * The sort direction comes straight from the query string and ends up in the
     * ORDER BY clause of the query, which is not escaped by the query builder.
     */
    public function testSortDirectionIsNotInjectable(): void
    {
        $request = new ServerRequest([
            'query' => [
                'iSortCol_0' => '2',
                'bSortable_2' => 'true',
                'sSortDir_0' => 'ASC, (SELECT SLEEP(5))',
            ],
        ]);

        $order = $this->OrderSearch->getOrder($this->columns, $request);

        $this->assertSame(
            [['error_name ASC']],
            $order,
            'A sort direction that is not ASC or DESC must be replaced, got: '
                . var_export($order, true),
        );
    }

    public function testSortDirectionIsKept(): void
    {
        foreach (['ASC' => 'ASC', 'DESC' => 'DESC', 'asc' => 'ASC', 'desc' => 'DESC'] as $sent => $expected) {
            $request = new ServerRequest([
                'query' => [
                    'iSortCol_0' => '2',
                    'bSortable_2' => 'true',
                    'sSortDir_0' => $sent,
                ],
            ]);

            $order = $this->OrderSearch->getOrder($this->columns, $request);
            $this->assertSame([['error_name ' . $expected]], $order);
        }
    }

    public function testNoSortColumnGivesNoOrder(): void
    {
        $request = new ServerRequest([
            'query' => [
                'iSortCol_0' => '0',
                'sSortDir_0' => 'ASC',
            ],
        ]);

        $this->assertSame([], $this->OrderSearch->getOrder($this->columns, $request));
    }
}
