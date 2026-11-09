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

use function date;
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
     */
    public array $validate = [
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
     * The developers a notification is created for.
     *
     * Read once by the ingest, which creates a report per error: reading them
     * again for every report was one scan and one query per developer, per
     * report.
     *
     * @return array<int, int> The ids of the developers
     */
    public static function developerIds(): array
    {
        $ids = [];
        foreach (TableRegistry::getTableLocator()->get('Developers')->find('all') as $developer) {
            $ids[] = (int) $developer->get('id');
        }

        return $ids;
    }

    /**
     * Create a notification for every developer, for one report.
     *
     * @param int                  $report_id    The report
     * @param array<int, int>|null $developerIds The developers, read when absent
     *
     * @return bool True on success, false on any type of failure
     */
    public static function addNotifications(int $report_id, array|null $developerIds = null): bool
    {
        $developerIds ??= self::developerIds();
        $notoficationTable = TableRegistry::getTableLocator()->get('Notifications');
        $res = true;
        foreach ($developerIds as $developerId) {
            $notification = $notoficationTable->newEmptyEntity();
            $notification->patch([
                'developer_id' => $developerId,
                'report_id' => $report_id,
                'created' => date('Y-m-d H:i:s', time()),
                'modified' => date('Y-m-d H:i:s', time()),
            ]);
            $res = (bool) $notoficationTable->save($notification);
        }

        return $res;
    }
}
