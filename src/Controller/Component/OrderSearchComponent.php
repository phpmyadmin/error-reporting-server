<?php

/**
 * Order and search component handling generation of ordering and
 * searching conditions in loading data tables.
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

namespace App\Controller\Component;

use Cake\Controller\Component;
use Cake\Http\ServerRequest;

use function array_keys;
use function count;
use function intval;

/**
 * Github api component handling comunication with github.
 */
class OrderSearchComponent extends Component
{
    /**
     * Indexes are +1'ed because first column is of checkboxes
     * and hence it should be ignored.
     *
     * @param string[] $aColumns The columns
     *
     * @return array
     */
    public function getSearchConditions(array $aColumns, ServerRequest $request): array
    {
        $searchConditions = ['OR' => []];
        $keys = array_keys($aColumns);
        $columnsCount = count($aColumns);

        $sSearch = $request->getQuery('sSearch');
        if ($sSearch !== '' && $sSearch !== null) {
            for ($i = 0; $i < $columnsCount; ++$i) {
                if ($request->getQuery('bSearchable_' . ($i + 1)) !== 'true') {
                    continue;
                }

                $searchConditions['OR'][] = [$aColumns[$keys[$i]] . ' LIKE' => '%' . $sSearch . '%'];
            }
        }

        /* Individual column filtering */
        for ($i = 0; $i < $columnsCount; ++$i) {
            $searchTerm = $request->getQuery('sSearch_' . ($i + 1));
            if ($searchTerm === '' || $searchTerm === null) {
                continue;
            }

            $searchConditions[] = [$aColumns[$keys[$i]] . ' LIKE' => $searchTerm];
        }

        return $searchConditions;
    }

    /**
     * @param string[] $aColumns The columns
     *
     * @return array|null
     */
    public function getOrder(array $aColumns, ServerRequest $request): ?array
    {
        if ($request->getQuery('iSortCol_0') !== null) {
            $order = [];
            //Seems like we need to sort with only one column each time, so no need to loop
            $sort_column_index = intval($request->getQuery('iSortCol_0'));

            $keys = array_keys($aColumns);

            $sortDir = $request->getQuery('sSortDir_0');
            if (
                $sort_column_index > 0
                && $request->getQuery('bSortable_' . $sort_column_index) === 'true'
            ) {
                $order[] = [$aColumns[$keys[$sort_column_index - 1]] . ' ' . $sortDir];
            }

            return $order;
        }

        return null;
    }
}
