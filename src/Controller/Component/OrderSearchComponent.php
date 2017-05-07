<?php
/* vim: set expandtab sw=4 ts=4 sts=4: */
/**
 * Order and search component handling generation of ordering and
 * searching conditions in loading data tables
 *
 * phpMyAdmin Error reporting server
 * Copyright (c) phpMyAdmin project (https://www.phpmyadmin.net/)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @package   Server.Controller.Component
 * @copyright Copyright (c) phpMyAdmin project (https://www.phpmyadmin.net/)
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 * @link      https://www.phpmyadmin.net/
 */
namespace App\Controller\Component;

use Cake\Controller\Component;
use Cake\Routing\Router;

/**
 * Github api component handling comunication with github
 *
 * @package       Server.Controller.Component
 */
class OrderSearchComponent extends Component {

    /**
     * Indexes are +1'ed because first column is of checkboxes
     * and hence it should be ingnored.
     * @param string[] $aColumns
     *
     * @return array
     */
    public function getSearchConditions($aColumns) {
        $searchConditions = array('OR' => array());
        $keys = array_keys($aColumns);

        if ( $this->request->query('sSearch') != "" ) {
            for ( $i = 0; $i < count($aColumns); $i++ ) {
                if ($this->request->query('bSearchable_' . ($i+1)) == "true") {
                    $searchConditions['OR'][] = array($aColumns[$keys[$i]] . " LIKE" =>
                            "%" . $this->request->query('sSearch') . "%");
                }
            }
        }

        /* Individual column filtering */
        for ( $i = 0; $i < count($aColumns); $i++ ) {
            if ($this->request->query('sSearch_' . ($i+1)) != '') {
                $searchConditions[] = array($aColumns[$keys[$i]] . " LIKE" =>
                        $this->request->query('sSearch_' . ($i+1)));
            }
        }
        return $searchConditions;
    }

    /**
     * @param string[] $aColumns
     *
     * @return array
     */
    public function getOrder($aColumns) {
        if ( $this->request->query('iSortCol_0') != null ) {
            $order = [];
            //Seems like we need to sort with only one column each time, so no need to loop
            $sort_column_index = intval($this->request->query('iSortCol_0'));

            $keys = array_keys($aColumns);

            if ($sort_column_index > 0
                && $this->request->query('bSortable_' . $sort_column_index) == "true"
            ) {
                $order[$aColumns[$keys[$sort_column_index - 1]]] = $this->request->query('sSortDir_0');
            }
            return $order;
        } else {
            return null;
        }
    }
}