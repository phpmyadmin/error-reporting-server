<?php

/**
 * Notification model representing a notification for new report.
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

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use InvalidArgumentException;
use function date;
use function is_int;
use function time;

/**
 * Notification Model.
 *
 * @property Developer $Developer
 * @property Report    $Report
 */
class NotificationsTable extends Table
{
    /**
     * Validation rules.
     *
     * @var array
     */
    public $validate = [
        'developer_id' => [
            'numeric' => [
                'rule' => ['numeric'],
                'allowEmpty' => false,
                'required' => true,
            ],
        ],
        'report_id' => [
            'numeric' => [
                'rule' => ['numeric'],
                'allowEmpty' => false,
                'required' => true,
            ],
        ],
    ];

    /**
     * The Associations below have been created with all possible keys,
     * those that are not needed can be removed.
     */

    /**
     * belongsTo associations.
     *
     * @param array $config Config array
     * @return void Nothing
     */
    public function initialize(array $config): void
    {
        $this->belongsTo('Reports', [
            'className' => 'Reports',
            'foreignKey' => 'report_id',
        ]);
    }

    /**
     * To Add Multiple Notifications for New report.
     *
     * @param int $report_id id of the new Report
     *
     * @return bool|object value. True on success. False on any type of failure.
     */
    public static function addNotifications(int $report_id)
    {
        if (! is_int($report_id)) {
            throw new InvalidArgumentException('Invalid Argument "$report_id"! Integer Expected.');
        }
        $devs = TableRegistry::getTableLocator()->get('Developers')->find('all');
        $notoficationTable = TableRegistry::getTableLocator()->get('Notifications');
        $res = true;
        foreach ($devs as $dev) {
            $notification = $notoficationTable->newEntity();
            $notification->developer_id = $dev['id'];
            $notification->report_id = $report_id;
            $notification->created = date('Y-m-d H:i:s', time());
            $notification->modified = date('Y-m-d H:i:s', time());
            $res = $notoficationTable->save($notification);
        }

        return $res;
    }
}
