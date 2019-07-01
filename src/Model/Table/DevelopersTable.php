<?php

/* vim: set expandtab sw=4 ts=4 sts=4: */
/**
 * A developer who has access to the system.
 *
 * phpMyAdmin Error reporting server
 * Copyright (c) phpMyAdmin project (https://www.phpmyadmin.net/)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) phpMyAdmin project (https://www.phpmyadmin.net/)
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 *
 * @see          https://www.phpmyadmin.net/
 */

namespace App\Model\Table;

use Cake\ORM\Table;

/**
 * A developer who has access to the system.
 */
class DevelopersTable extends Table
{
    /**
     * creates a developer record given his github info and his access token.
     *
     * @param array                            $githubInfo  the data gitub has on this developer
     * @param string                           $accessToken this developer's access token
     * @param \Cake\Datasource\EntityInterface $developer   the developper
     *
     * @return bool true if the developer was correctly saved otherwise false
     */
    public function saveFromGithub($githubInfo, $accessToken, $developer)
    {
        $developer->full_name = $githubInfo['name'];
        $developer->gravatar_id = $githubInfo['gravatar_id'];
        $developer->email = $githubInfo['email'];
        $developer->github_id = $githubInfo['id'];
        $developer->access_token = $accessToken;
        $developer->has_commit_access = $githubInfo['has_commit_access'] ? 1 : 0;
        if ($this->save($developer)) {
            return $developer->id;
        }
    }
}
