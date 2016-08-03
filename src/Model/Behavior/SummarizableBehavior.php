<?php
/* vim: set noexpandtab sw=2 ts=2 sts=2: */
namespace app\Model\Behavior;

use Cake\Model\Behavior;
use Cake\Model\Model;

/**
 * Summarizable behaviour
 * A behavior allowing models to be summarized by a field
 *
 * phpMyAdmin Error reporting server
 * Copyright (c) phpMyAdmin project (https://www.phpmyadmin.net/)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) phpMyAdmin project (https://www.phpmyadmin.net/)
 * @package       Server.Model.Behavior
 * @link          https://www.phpmyadmin.net/
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
/**
 * Summarizable behaviour
 * A behavior allowing models to be summarized by a field
 * @package       Server.Model.Behavior
 */
class SummarizableBehavior extends ModelBehavior {

		public $mapMethods = array('/\b_findGroupedCount\b/' => 'findGroupedCount');

		public function setup(Model $model, $config = array()) {
			$model->findMethods['groupedCount'] = true;
		}

		function findGroupedCount(Model $model, $method, $state, $query,
				$results = array()) {
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
