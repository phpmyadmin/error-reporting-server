<?php
/**
 * NotificationFixture.
 */

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class NotificationsFixture extends TestFixture
{
    /**
     * Imports.
     *
     * @var array
     */
    public $import = ['table' => 'notifications'];

    /**
     * Records.
     *
     * @var array
     */
    public $records = [
        [
            'id' => 1,
            'developer_id' => 1,
            'report_id' => 1,
            'created' => '2014-01-01 07:05:09',
            'modified' => '2014-01-01 07:05:09',
        ],
        [
            'id' => 2,
            'developer_id' => 1,
            'report_id' => 4,
            'created' => '2014-01-02 07:05:09',
            'modified' => '2014-01-03 07:05:09',
        ],
        [
            'id' => 3,
            'developer_id' => 2,
            'report_id' => 4,
            'created' => '2014-07-02 07:05:09',
            'modified' => '2014-07-03 07:05:09',
        ],
    ];
}
