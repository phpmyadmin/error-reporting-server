<?php

declare(strict_types=1);

/**
 * Configures forwarding reports to Sentry
 */

return [
    /*'Forwarding.Sentry' => [
        'base_url' => 'https://sentry.domain.tld',// Without the last /
        'project_id' => 2,// Settings > Security headers (can be found in the URL: api/{project_id}/security)
        'key' => 'xxxxxxxxxxxxxxxxxx',// Settings > Security headers
        'secret' => 'xxxxxxxxxxxxxxxxxxxx',// Settings > Client Keys > DSN (deprecated, use password value of the http basic auth)
        // Used to send user feedback
        'dsn_url' => 'https://xxxxxxxxxxxxxxxxxx@sentry.domain.tld/{project_id}',// Settings > Client Keys > DSN
    ],*/
    'Forwarding.Sentry' => null,// No forwarding
];
