<?php
/* vim: set noexpandtab sw=2 ts=2 sts=2: */
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
