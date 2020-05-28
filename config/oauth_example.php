<?php
/**
 * Configures github application details for authentication. You can get them
 * from the application configuration page on github.
 */

namespace App\Config;

use Cake\Core\Configure;

Configure::write('GithubConfig', [
    'client_id' => '<application-client-id>',
    'client_secret' => '<application-client-secret>',
]);

/**
 * Configures the github repo to check commit access for
 */
Configure::write('GithubRepoPath', 'phpmyadmin/phpmyadmin');

/**
 * Access token for syncing Github issue states
 */
Configure::write('GithubAccessToken', '<access-token>');
