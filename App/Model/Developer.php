<?php
/* vim: set noexpandtab sw=2 ts=2 sts=2: */
namespace app\Model;

use App\Model\AppModel;
/**
 * A developer who has access to the system
 *
 * phpMyAdmin Error reporting server
 * Copyright (c) phpMyAdmin project (http://www.phpmyadmin.net)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) phpMyAdmin project (http://www.phpmyadmin.net)
 * @package       Server.Model
 * @link          http://www.phpmyadmin.net
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */


/**
 * A developer who has access to the system
 *
 * @package       Server.Model
 */
class Developer extends AppModel {

/**
 * creates a developer record given his github info and his access token
 *
 * @param Array $githubInfo the data gitub has on this developer
 * @param String $accessToken this developer's access token
 * @return Boolean true if the developer was correctly saved otherwise false
 */
	public function saveFromGithub($githubInfo, $accessToken) {
		$userData = array(
			'full_name' => $githubInfo['name'],
			'gravatar_id' => $githubInfo['gravatar_id'],
			'email' => $githubInfo['email'],
			'github_id' => $githubInfo['id'],
			'access_token' => $accessToken,
			'has_commit_access' => $githubInfo['has_commit_access']?1:0,
		);
		return $this->save($userData);
	}
}
