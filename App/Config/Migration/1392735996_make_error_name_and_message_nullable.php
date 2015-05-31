<?php
namespace app\Config\Migration;

class MakeErrorNameAndMessageNullable extends CakeMigration {

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
			'alter_field' => array(
				'incidents' => array(
					'error_name' => array('type' => 'string', 'null' => true, 'length' => 30, 'collate' => 'utf8_general_ci', 'charset' => 'utf8'),
					'error_message' => array('type' => 'string', 'null' => true, 'length' => 100, 'collate' => 'utf8_general_ci', 'charset' => 'utf8'),
					'report_id' => array('type' => 'integer', 'null' => false, 'default' => NULL, 'length' => 10),
				),
			),
		),
		'down' => array(
			'alter_field' => array(
				'incidents' => array(
					'error_name' => array('type' => 'string', 'null' => false, 'length' => 30, 'collate' => 'utf8_general_ci', 'charset' => 'utf8'),
					'error_message' => array('type' => 'string', 'null' => false, 'length' => 100, 'collate' => 'utf8_general_ci', 'charset' => 'utf8'),
					'report_id' => array('type' => 'integer', 'null' => true, 'default' => NULL, 'length' => 10),
				),
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
