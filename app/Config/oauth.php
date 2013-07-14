<?php
/**
 * Configures github application details for authentication
 */
Configure::write('GithubConfig', array(
  'client_id' => 'd179923f7b17a7aadf35',
  'client_secret' => '6ad9cc59aa445c2b4c13b5a44f6b71b41dcabdfb'
));

/**
 * Configures the github repo to check commit access
 */
Configure::write('GithubRepoPath', 'm0hamed/phpmyadmin');
/**
 * Configures sourceforge application details for authentication
 */
Configure::write('SourceForgeConfig', array(
  'consumer_key' => '18812a8cf33f6729c2dd',
  'consumer_secret' => 'ce2b17b27a7e10cec530323ef863edc6a24f305418d276f0e197c7e5bb0b70c6d8cecbca3eff0bb2'
));

/**
 * Configures sourceforge credentials
 */
Configure::write('SourceForgeCredentials', array(
  'key' => 'b9404a915db97d594cac',
  'secret' => '2af63d5258742d4f0c9820c26252b435210faf58d0e2af2a248e1b11ce0d92ca80cba6bba98e0828'
));
