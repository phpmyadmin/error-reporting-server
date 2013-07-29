<?php
/**
 * Configures github application details for authentication. You can get them
 * from the application configuration page on github.
 */
Configure::write('GithubConfig', array(
  'client_id' => '<application-client-id>',
  'client_secret' => '<application-client-secret>'
));

/**
 * Configures the github repo to check commit access for
 */
Configure::write('GithubRepoPath', 'phpmyadmin/phpmyadmin');

/**
 * Configures sourceforge application details for authentication
 */
Configure::write('SourceForgeConfig', array(
  'consumer_key' => '<application-consumer-key>',
  'consumer_secret' => '<application-consumer-secret>'
));

/**
 * Configures sourceforge access token for the account that submits the reports.
 * You can use the sourceforge/authorize action to get access token for any
 * user. You can then enter the resultant token here
 */
Configure::write('SourceForgeCredentials', array(
  'key' => 'b9404a915db97d594cac',
  'secret' => '2af63d5258742d4f0c9820c26252b435210faf58d0e2af2a248e1b11ce0d92ca80cba6bba98e0828'
));
