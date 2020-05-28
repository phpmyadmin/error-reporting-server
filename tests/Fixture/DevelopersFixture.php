<?php
/**
 * DeveloperFixture.
 */

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class DevelopersFixture extends TestFixture
{
    /**
     * Imports.
     *
     * @var array
     */
    public $import = ['table' => 'developers'];

    /**
     * Records.
     *
     * @var array
     */
    public $records = [
        [
            'id' => 1,
            'github_id' => 1,
            'full_name' => 'Lorem ipsum dolor sit amet',
            'email' => 'Lorem ipsum dolor sit amet',
            'gravatar_id' => 'Lorem ipsum dolor sit amet',
            'access_token' => 'Lorem ipsum dolor sit amet',
            'created' => '2013-08-29 22:11:02',
            'modified' => '2013-08-29 22:11:02',
            'has_commit_access' => 1,
        ],
        [
            'id' => 2,
            'github_id' => 2,
            'full_name' => 'Lorem ipsum dolor sit amet',
            'email' => 'Lorem ipsum dolor sit amet',
            'gravatar_id' => 'Lorem ipsum dolor sit amet',
            'access_token' => 'Lorem ipsum dolor sit amet',
            'created' => '2013-08-29 22:11:02',
            'modified' => '2013-08-29 22:11:02',
            'has_commit_access' => 0,
        ],
    ];
}
