<?php

/* vim: set expandtab sw=4 ts=4 sts=4: */
/**
 * Summarizable behaviour
 * A behavior allowing models to be summarized by a field.
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

namespace app\Model\Behavior;

use Cake\Model\Behavior;
use Cake\Model\Model;

/**
 * Summarizable behaviour
 * A behavior allowing models to be summarized by a field.
 */
class SummarizableBehavior extends ModelBehavior
{
    public $mapMethods = array('/\b_findGroupedCount\b/' => 'findGroupedCount');

    public function setup(Model $model, $config = array())
    {
        $model->findMethods['groupedCount'] = true;
    }

    public function findGroupedCount(
        Model $model,
        $method,
        $state,
        $query,
        $results = array()
    ) {
        if ($state === 'before') {
            return $query;
        }
        $output = array();
        foreach ($results as $row) {
            foreach ($row[$model->name] as $key => $value) {
                $output[$value] = $row[0]['count'];
            }
        }

        return $output;
    }
}
