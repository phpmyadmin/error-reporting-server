<?php
namespace app\Config\Migration;

class AddFilenameAndLinenumberToReports extends CakeMigration {

/**
 * Migration description
 *
 * @var string
 * @access public
 */
	public $description = '';

/**
 * Actions to be performed
 *
 * @var array $migration
 * @access public
 */
	public $migration = array(
		'up' => array(
			'create_field' => array(
				'reports' => array(
					'filename' => array('type' => 'text', 'null' => false, 'default' => NULL, 'collate' => 'utf8_general_ci', 'charset' => 'utf8', 'after' => 'related_report_id'),
					'linenumber' => array('type' => 'integer', 'null' => false, 'default' => NULL, 'after' => 'filename'),
				),
			),
		),
		'down' => array(
			'drop_field' => array(
				'reports' => array('filename', 'linenumber',),
			),
		),
	);

/**
 * Before migration callback
 *
 * @param string $direction, up or down direction of migration process
 * @return boolean Should process continue
 * @access public
 */
	public function before($direction) {
		return true;
	}

/**
 * After migration callback
 *
 * @param string $direction, up or down direction of migration process
 * @return boolean Should process continue
 * @access public
 */
	public function after($direction) {
		return true;
	}
}
