<?php

declare(strict_types=1);

/**
 * Configures forwarding reports to a sentry compatible tracker (Rustrak)
 */

return [
    /*'Forwarding.Sentry' => [
        // The root of the tracker, without the last / and without /api
        'base_url' => 'http://127.0.0.1:9000',
        // The id of the project: POST {base_url}/api/{project_id}/envelope/
        'project_id' => 1,
        // The public key of the project key (the user part of its DSN)
        'key' => 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
    ],*/
    'Forwarding.Sentry' => null,// No forwarding
];
