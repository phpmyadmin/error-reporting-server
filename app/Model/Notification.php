<?php
/* vim: set noexpandtab sw=2 ts=2 sts=2: */
/**
 * Notification model representing a notification for new report.
 *
 * phpMyAdmin Error reporting server
 * Copyright (c) phpMyAdmin project (http://www.phpmyadmin.net)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) phpMyAdmin project (http://www.phpmyadmin.net)
 * @package       Server.Model
 * @link          http://www.phpmyadmin.net
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

App::uses('AppModel', 'Model');
App::import('model','Developer');
/**
 * Notification Model
 *
 * @property Developer $Developer
 * @property Report $Report
 */
class Notification extends AppModel {

/**
 * Validation rules
 *
 * @var array
 */
	public $validate = array(
		'developer_id' => array(
			'numeric' => array(
				'rule' => array('numeric'),
				'allowEmpty' => false,
				'required' => true,
			),
		),
		'report_id' => array(
			'numeric' => array(
				'rule' => array('numeric'),
				'allowEmpty' => false,
				'required' => true,
			),
		),
	);

	//The Associations below have been created with all possible keys, those that are not needed can be removed

/**
 * belongsTo associations
 *
 * @var array
 */
	public $belongsTo = array(
		'Developer' => array(
			'className' => 'Developer',
			'foreignKey' => 'developer_id',
			'conditions' => '',
			'fields' => '',
			'order' => ''
		),
		'Report' => array(
			'className' => 'Report',
			'foreignKey' => 'report_id',
			'conditions' => '',
			'fields' => '',
			'order' => ''
		)
	);

	public static function addNotifications($report_id)
	{
		if (!is_int($report_id)) {
			throw new InvalidArgumentException('Invalid Argument "$report_id"! Integer Expected.');
		}
		$devs = new Developer();
		$devs = $devs->find('all');
		$notifications = array();
		foreach ($devs as $dev) {
			$notification = array(
				'developer_id' => $dev['Developer']['id'],
				'report_id' => $report_id
			);
			array_push($notifications,$notification);
		}
		$res = true;
		// Following check is necessary in case there're no 'Developers' in the table
		if ($notifications) {
			$notificationObj = new Notification();
			$res = $notificationObj->saveMany($notifications);
		}
		return($res);
	}
}
