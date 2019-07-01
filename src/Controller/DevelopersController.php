<?php

/* vim: set expandtab sw=4 ts=4 sts=4: */
/**
 * Developer controller handling developer login/logout/register.
 *
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

namespace App\Controller;

use Cake\Core\Configure;
use Cake\Event\Event;

/**
 * Developer controller handling developer login/logout/register.
 */
class DevelopersController extends AppController
{
    public $helpers = [
        'Html',
        'Form',
    ];

    public $components = [
        'GithubApi',
    ];

    public function beforeFilter(Event $event)
    {
        parent::beforeFilter($event);
        $this->GithubApi->githubConfig = Configure::read('GithubConfig');
        $this->GithubApi->githubRepo = Configure::read('GithubRepoPath');
    }

    public function login()
    {
        $url = $this->GithubApi->getRedirectUrl('user:email,public_repo');
        $this->redirect($url);
    }

    public function callback()
    {
        $code = $this->request->query('code');
        $accessToken = $this->GithubApi->getAccessToken($code);
        if ($code && $accessToken) {
            list($userInfo, $status) = $this->GithubApi->getUserInfo($accessToken);
            if ($status != 200) {
                $flash_class = 'alert alert-error';
                $this->Flash->default(
                    $userInfo['message'],
                    ['params' => ['class' => $flash_class]]
                );

                $this->redirect('/');
                return;
            } else {
                $userInfo['has_commit_access'] = $this->GithubApi->canCommitTo(
                    $userInfo['login'],
                    $this->GithubApi->githubRepo,
                    Configure::read('GithubAccessToken')
                );

                $this->_authenticateDeveloper($userInfo, $accessToken);

                $flash_class = 'alert alert-success';
                $this->Flash->default(
                    'You have been logged in successfully',
                    ['params' => ['class' => $flash_class]]
                );
            }
        } else {
            $flash_class = 'alert alert-error';
            $this->Flash->default(
                'We were not able to authenticate you.'
                    . ' Please try again later',
                ['params' => ['class' => $flash_class]]
            );

            $this->redirect('/');
            return;
        }

        $last_page = $this->request->session()->read('last_page');
        if (empty($last_page)) {
            $last_page = [
                'controller' => 'reports',
                'action' => 'index'
            ];
        }
        $this->redirect($last_page);
    }

    public function logout()
    {
        $this->request->session()->destroy();

        $flash_class = 'alert alert-success';
        $this->Flash->default(
            'You have been logged out successfully',
            ['params' => ['class' => $flash_class]]
        );
        $this->redirect('/');
    }

    protected function _authenticateDeveloper($userInfo, $accessToken)
    {
        $developers = $this->Developers->findByGithubId($userInfo['id']);
        $developer = $developers->all()->first();
        if (! $developer) {
            $developer = $this->Developers->newEntity();
        } else {
            $this->Developers->id = $developer['id'];
        }
        $this->Developers->id = $this->Developers->saveFromGithub($userInfo, $accessToken, $developer);
        $this->request->session()->write('Developer.id', $this->Developers->id);
        $this->request->session()->write('access_token', $accessToken);
        $this->request->session()->write('read_only', ! ($userInfo['has_commit_access']));
    }
}
