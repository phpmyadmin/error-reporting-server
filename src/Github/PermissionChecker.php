<?php

/**
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

namespace App\Github;

use Cake\Http\Client;

use function in_array;
use function is_array;
use function is_string;

/**
 * Reads the permission a developer has on the repository the reports are filed
 * in.
 *
 * The middleware needs it outside a controller, so it does not go through the
 * component the controllers use.
 */
class PermissionChecker
{
    /**
     * Seconds a permission read is believed before it is asked again.
     */
    public const RECHECK_INTERVAL = 900;

    /**
     * Roles of a repository that are allowed to act on the reports.
     */
    public const WRITE_ROLES = ['admin', 'maintain', 'write'];

    /**
     * Read the permission of a developer on a repository.
     *
     * @param string      $login    GitHub login of the developer
     * @param string      $repoPath The repository, "owner/name"
     * @param string      $token    Token used to ask the API
     * @param Client|null $client   HTTP client, one is built when absent
     *
     * @return bool|null True when the developer may write, null when the API
     *                   could not be asked, so a caller keeps what it knew
     */
    public static function canCommitTo(
        string $login,
        string $repoPath,
        string $token,
        Client|null $client = null,
    ): bool|null {
        if ($login === '' || $repoPath === '' || $token === '') {
            return null;
        }

        $client ??= new Client([
            'headers' => [
                'Accept' => 'application/vnd.github+json',
                'User-Agent' => 'phpmyadmin-error-reporting-server',
                'Authorization' => 'token ' . $token,
            ],
            'timeout' => 5,
        ]);

        $response = $client->get(
            'https://api.github.com/repos/' . $repoPath . '/collaborators/' . $login . '/permission',
        );

        if ($response->getStatusCode() !== 200) {
            // A refusal is an answer: the developer may not write. Anything
            // else, a 5xx or a network failure, is not.
            return $response->getStatusCode() === 404 ? false : null;
        }

        $data = $response->getJson();
        if (! is_array($data)) {
            return null;
        }

        $role = $data['permission'] ?? $data['role_name'] ?? null;

        return is_string($role) && in_array($role, self::WRITE_ROLES, true);
    }
}
