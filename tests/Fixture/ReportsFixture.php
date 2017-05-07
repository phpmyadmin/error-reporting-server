<?php

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class ReportsFixture extends TestFixture
{
    public $connection = 'test';
    public $import = array('table' => 'reports', 'connection' => 'test');

    public $records = array(
        array(
            'id' => 1,
            'error_message' => 'Lorem ipsum dolor sit amet',
            'error_name' => 'error2',
            'pma_version' => '4.0',
            'status' => 'new',
            'location' => 'Lorem ipsum dolor sit amet, aliquet feugiat. Convallis morbi fringilla gravida, phasellus feugiat dapibus velit nunc, pulvinar eget sollicitudin venenatis cum nullam, vivamus ut a sed, mollitia lectus. Nulla vestibulum massa neque ut et, id hendrerit sit, feugiat in taciti enim proin nibh, tempor dignissim, rhoncus duis vestibulum nunc mattis convallis.',
            'linenumber' => 1,
            'sourceforge_bug_id' => 1,
            'related_to' => null,
            'created' => '2013-08-28 21:47:17',
            'modified' => '2013-08-28 21:47:17',
        ),
        array(
            'id' => 2,
            'error_message' => 'Lorem ipsum dolor sit amet',
            'error_name' => 'error2',
            'pma_version' => '4.0',
            'status' => 'new',
            'location' => 'Lorem ipsum dolor sit amet, aliquet feugiat. Convallis morbi fringilla gravida, phasellus feugiat dapibus velit nunc, pulvinar eget sollicitudin venenatis cum nullam, vivamus ut a sed, mollitia lectus. Nulla vestibulum massa neque ut et, id hendrerit sit, feugiat in taciti enim proin nibh, tempor dignissim, rhoncus duis vestibulum nunc mattis convallis.',
            'linenumber' => 2,
            'sourceforge_bug_id' => 2,
            'related_to' => null,
            'created' => '2013-08-28 21:47:17',
            'modified' => '2013-08-28 21:47:17',
        ),
        array(
            'id' => 4,
            'error_message' => 'Lorem ipsum dolor sit amet',
            'error_name' => 'error1',
            'pma_version' => '3.8',
            'status' => 'new',
            'location' => 'Lorem ipsum dolor sit amet, aliquet feugiat. Convallis morbi fringilla gravida, phasellus feugiat dapibus velit nunc, pulvinar eget sollicitudin venenatis cum nullam, vivamus ut a sed, mollitia lectus. Nulla vestibulum massa neque ut et, id hendrerit sit, feugiat in taciti enim proin nibh, tempor dignissim, rhoncus duis vestibulum nunc mattis convallis.',
            'linenumber' => 4,
            'sourceforge_bug_id' => 4,
            'related_to' => null,
            'created' => '2013-08-28 21:47:17',
            'modified' => '2013-08-28 21:47:17',
        ),
    );
}
