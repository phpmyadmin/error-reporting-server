<?php
/**
 * NotificationFixture.
 */

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class NotificationsFixture extends TestFixture
{
    /**
     * Fields.
     *
     * @var array
     */
    /*public $fields = array(
        'id' => array('type' => 'integer', 'null' => false, 'default' => null, 'length' => 10, 'unsigned' => false, 'key' => 'primary'),
        'developer_id' => array('type' => 'integer', 'null' => false, 'default' => null, 'length' => 10, 'unsigned' => false),
        'report_id' => array('type' => 'integer', 'null' => false, 'default' => null, 'length' => 10, 'unsigned' => false),
        'created' => array('type' => 'datetime', 'null' => true, 'default' => null),
        'modified' => array('type' => 'datetime', 'null' => true, 'default' => null),
        'indexes' => array(
            'PRIMARY' => array('column' => 'id', 'unique' => 1)
        ),
        'tableParameters' => array('charset' => 'utf8', 'collate' => 'utf8_general_ci', 'engine' => 'InnoDB')
    );*/
    public $import = array('table' => 'notifications', 'connection' => 'test');
    /**
     * Records.
     *
     * @var array
     */
    public $records = array(
        array(
            'id' => 1,
            'developer_id' => 1,
            'report_id' => 1,
            'created' => '2014-01-01 07:05:09',
            'modified' => '2014-01-01 07:05:09',
        ),
        array(
            'id' => 2,
            'developer_id' => 1,
            'report_id' => 4,
            'created' => '2014-01-02 07:05:09',
            'modified' => '2014-01-03 07:05:09',
        ),
        array(
            'id' => 3,
            'developer_id' => 1,
            'report_id' => 4,
            'created' => '2014-07-02 07:05:09',
            'modified' => '2014-07-03 07:05:09',
        ),
    );
}
