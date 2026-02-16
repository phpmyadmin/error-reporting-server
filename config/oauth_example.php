<?php

declare(strict_types=1);

/**
 * Configures github application details for authentication. You can get them
 * from the application configuration page on github.
 */

return [
    'GithubConfig' => [
        'client_id' => '<application-client-id>',
        'client_secret' => '<application-client-secret>',
    ],

    /**
     * Configures the github repo to check commit access for
     */
    'GithubRepoPath' => 'phpmyadmin/phpmyadmin',

    /**
     * Access token for syncing Github issue states
     */
    'GithubAccessToken' => '<access-token>',

];
