<?php
namespace app\Config\Migration;

class AddDifferentStacktraceFlagToIncidents extends CakeMigration {

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
				'incidents' => array(
					'different_stacktrace' => array('type' => 'boolean', 'null' => false, 'default' => '0', 'after' => 'full_report'),
				),
			),
		),
		'down' => array(
			'drop_field' => array(
				'incidents' => array('different_stacktrace',),
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
