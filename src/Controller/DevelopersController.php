<?php

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
use Cake\Http\Response;
use App\Controller\Component\GithubApiComponent;

/**
 * Developer controller handling developer login/logout/register.
 *
 * @property GithubApiComponent $GithubApi
 */
class DevelopersController extends AppController
{
    /**
     * Initialization hook method.
     *
     * Use this method to add common initialization code like loading components.
     *
     * @return void Nothing
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('GithubApi');
        $this->viewBuilder()->setHelpers([
            'Html',
            'Form',
        ]);
    }

    public function login(): void
    {
        $url = $this->GithubApi->getRedirectUrl('user:email,public_repo');
        $this->redirect($url);
    }

    public function callback(): ?Response
    {
        $code = $this->request->getQuery('code');
        $accessToken = $this->GithubApi->getAccessToken($code);
        if (empty($code) || empty($accessToken)) {
            $flash_class = 'alert alert-error';
            $this->Flash->set(
                'We were not able to authenticate you.'
                    . ' Please try again later',
                ['params' => ['class' => $flash_class]]
            );

            return $this->redirect('/');
        }

        [$userInfo, $status] = $this->GithubApi->getUserInfo($accessToken);
        if ($status !== 200) {
            $flash_class = 'alert alert-error';
            $this->Flash->set(
                $userInfo['message'],
                ['params' => ['class' => $flash_class]]
            );

            return $this->redirect('/');
        }

        $userInfo['has_commit_access'] = $this->GithubApi->canCommitTo(
            $userInfo['login'],
            Configure::read('GithubRepoPath'),
            Configure::read('GithubAccessToken')
        );

        $this->authenticateDeveloper($userInfo, $accessToken);

        $flash_class = 'alert alert-success';
        $this->Flash->set(
            'You have been logged in successfully',
            ['params' => ['class' => $flash_class]]
        );

        $last_page = $this->request->getSession()->read('last_page');
        if (empty($last_page)) {
            $last_page = [
                'controller' => 'reports',
                'action' => 'index',
            ];
        }

        return $this->redirect($last_page);
    }

    public function logout(): void
    {
        $this->request->getSession()->destroy();

        $flash_class = 'alert alert-success';
        $this->Flash->set(
            'You have been logged out successfully',
            ['params' => ['class' => $flash_class]]
        );
        $this->redirect('/');
    }

    protected function authenticateDeveloper(array $userInfo, string $accessToken): void
    {
        $developers = $this->Developers->findByGithubId($userInfo['id']);
        $developer = $developers->all()->first();
        if (! $developer) {
            $developer = $this->Developers->newEmptyEntity();
        } else {
            $this->Developers->id = $developer['id'];
        }
        $this->Developers->id = $this->Developers->saveFromGithub($userInfo, $accessToken, $developer);
        $this->request->getSession()->write('Developer.id', $this->Developers->id);
        $this->request->getSession()->write('access_token', $accessToken);
        $this->request->getSession()->write('read_only', ! $userInfo['has_commit_access']);
    }
}
