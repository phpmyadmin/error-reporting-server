<?php

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

use Cake\Datasource\EntityInterface;
use Cake\ORM\Table;

use function is_int;

/**
 * A developer who has access to the system.
 */
class DevelopersTable extends Table
{
    /**
     * creates a developer record given his github info.
     *
     * The GitHub access token is not stored: it is only kept in the session
     * for the current login and never written in plain text in the database.
     *
     * @param array           $githubInfo the data gitub has on this developer
     * @param EntityInterface $developer  the developper
     *
     * @return int|null The developer Id
     */
    public function saveFromGithub(array $githubInfo, EntityInterface $developer): int|null
    {
        $developer->patch([
            'full_name' => $githubInfo['name'],
            'gravatar_id' => $githubInfo['gravatar_id'],
            // The login no longer asks for user:email, so /user only returns
            // the address when the developer made it public.
            'email' => $githubInfo['email'] ?? null,
            'github_id' => $githubInfo['id'],
            'has_commit_access' => $githubInfo['has_commit_access'] ? 1 : 0,
        ]);
        if ($this->save($developer)) {
            $id = $developer->get('id');

            return is_int($id) ? $id : null;
        }

        return null;
    }
}
