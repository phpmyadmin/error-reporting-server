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

use App\Controller\Component\GithubApiComponent;
use Cake\Core\Configure;
use Cake\Datasource\EntityInterface;
use Cake\Http\Response;

use function bin2hex;
use function hash_equals;
use function is_array;
use function is_string;
use function parse_url;
use function random_bytes;
use function str_starts_with;
use function strcasecmp;
use function time;

use const PHP_URL_HOST;

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

    public function login(): Response
    {
        $session = $this->request->getSession();
        $session->destroy();

        $state = bin2hex(random_bytes(32));
        $session->write('oauth_state', $state);

        $url = $this->GithubApi->getRedirectUrl('public_repo', $state);

        return $this->redirect($url);
    }

    public function callback(): Response|null
    {
        $session = $this->request->getSession();
        $state = $this->request->getQuery('state');
        $expectedState = $session->read('oauth_state');
        // The state is single use, whether the check succeeds or not.
        $session->delete('oauth_state');

        if (
            ! is_string($state)
            || ! is_string($expectedState)
            || $expectedState === ''
            || ! hash_equals($expectedState, $state)
        ) {
            $flash_class = 'alert alert-error';
            $this->Flash->set(
                'We were not able to verify your login attempt.'
                    . ' Please try again later',
                ['params' => ['class' => $flash_class]],
            );

            return $this->redirect('/');
        }

        $code = $this->request->getQuery('code');
        $accessToken = $this->GithubApi->getAccessToken($code);
        if (empty($code) || empty($accessToken)) {
            $flash_class = 'alert alert-error';
            $this->Flash->set(
                'We were not able to authenticate you.'
                    . ' Please try again later',
                ['params' => ['class' => $flash_class]],
            );

            return $this->redirect('/');
        }

        [$userInfo, $status] = $this->GithubApi->getUserInfo($accessToken);
        if ($status !== 200) {
            $flash_class = 'alert alert-error';
            $this->Flash->set(
                $userInfo['message'],
                ['params' => ['class' => $flash_class]],
            );

            return $this->redirect('/');
        }

        $userInfo['has_commit_access'] = $this->GithubApi->canCommitTo(
            $userInfo['login'],
            Configure::read('GithubRepoPath'),
            $accessToken,
        );

        $this->authenticateDeveloper($userInfo, $accessToken);

        $flash_class = 'alert alert-success';
        $this->Flash->set(
            'You have been logged in successfully',
            ['params' => ['class' => $flash_class]],
        );

        $last_page = $this->request->getSession()->read('last_page');
        // Only redirect inside this application after the login.
        if (is_array($last_page) || (is_string($last_page) && $this->isLocalUrl($last_page))) {
            return $this->redirect($last_page);
        }

        return $this->redirect([
            'controller' => 'reports',
            'action' => 'index',
        ]);
    }

    /**
     * Whether a URL points inside this application.
     *
     * A relative path is accepted, as is an absolute URL whose host is the
     * configured application host. Anything else, including protocol relative
     * URLs, is rejected.
     *
     * @param string $url the URL to check
     *
     * @return bool true when the URL belongs to this application
     */
    protected function isLocalUrl(string $url): bool
    {
        if (str_starts_with($url, '/')) {
            return ! str_starts_with($url, '//');
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host)) {
            return false;
        }

        $baseHost = parse_url((string) Configure::read('App.fullBaseUrl'), PHP_URL_HOST);

        return is_string($baseHost) && strcasecmp($host, $baseHost) === 0;
    }

    public function logout(): Response
    {
        // A link on another site must not be able to sign the developer out.
        $this->request->allowMethod(['post']);

        $this->request->getSession()->destroy();

        $flash_class = 'alert alert-success';
        $this->Flash->set(
            'You have been logged out successfully',
            ['params' => ['class' => $flash_class]],
        );

        return $this->redirect('/');
    }

    protected function authenticateDeveloper(array $userInfo, string $accessToken): void
    {
        $developer = $this->Developers->findByGithubId($userInfo['id'])->first();
        if (! $developer instanceof EntityInterface) {
            $developer = $this->Developers->newEmptyEntity();
        }

        $developerId = $this->Developers->saveFromGithub($userInfo, $developer);
        $session = $this->request->getSession();
        $session->renew();
        $session->write('Developer.id', $developerId);
        $session->write('access_token', $accessToken);
        // The login is what the API is asked about later: the permission is
        // read again by the middleware, see AuthenticationMiddleware.
        $session->write('github_login', (string) ($userInfo['login'] ?? ''));
        $session->write('has_commit_access', (bool) $userInfo['has_commit_access']);
        $session->write('has_commit_access_at', time());
        $session->write('access_checked_at', time());
        $session->write('read_only', ! $userInfo['has_commit_access']);
    }
}
