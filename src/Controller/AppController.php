<?php

/**
 * Application level Controller.
 *
 * This file is application-wide controller file. You can put all
 * application-wide controller-related methods here.
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

use Cake\Controller\Controller;
use Cake\Event\Event;
use Cake\Http\Response;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;
use function in_array;

/**
 * Application Controller.
 *
 * Add your application-wide methods in the class below, your controllers
 * will inherit them.
 *
 * @see    http://book.cakephp.org/2.0/en/controllers.html#the-app-controller
 */
class AppController extends Controller
{
    /** @var string[] */
    public $uses = [
        'Developer',
        'Notification',
    ];

    /** @var array */
    public $whitelist = [
        'Developers',
        'Pages',
        'Incidents' => ['create'],
        'Events',
    ];

    /** @var array */
    public $readonly_whitelist = [
        'Developers',
        'Pages',
        'Reports' => [
            'index',
            'view',
            'data_tables',
        ],
        'Incidents' => ['view'],
    ];

    /** @var string[] */
    public $css_files = [
        'jquery.dataTables',
        'jquery.dataTables_themeroller',
        'bootstrap.min',
        'bootstrap-responsive.min',
        'shCore',
        'shThemeDefault',
        'custom',
    ];

    /** @var string[] */
    public $js_files = [
        'jquery',
        'jquery.dataTables.min',
        'bootstrap',
        'shCore',
        'shBrushXml',
        'shBrushJScript',
        'shBrushPhp',
        'raphael-min',
        'g.raphael-min',
        'g.pie-min',
        'g.line-min',
        'g.bar-min',
        'g.dot-min',
        'jquery.jqplot.min',
        'jqplot.barRenderer.min',
        'jqplot.highlighter.min',
        'jqplot.dateAxisRenderer.min',
        'jqplot.categoryAxisRenderer.min',
        'jqplot.pointLabels.min',
        'jqplot.canvasTextRenderer.min',
        'jqplot.canvasAxisTickRenderer.min',
        'jqplot.cursor.min',
        'pie',
        'custom',
    ];

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
        $this->loadComponent('Flash');
        /*  $this->loadComponent(
                'Auth', [
                    'loginAction' => [
                        'controller' => 'Developer',
                        'action' => 'login'
                    ],
                    'authError' => 'Did you really think you are allowed to see that?',
                    'authenticate' => [
                        'Form' => [
                            'fields' => ['username' => 'email']
                        ]
                    ]
                ]
            );
        */
    }

    public function beforeFilter(Event $event): void
    {
        $controller = $this->request->controller;
        $this->set('current_controller', $controller);
        $notif_count = 0;

        if ($this->request->getSession()->read('Developer.id')) {
            $this->checkReadonlyAccess();

            $current_developer = TableRegistry::getTableLocator()->get('Developers')->
                    findById($this->request->getSession()->read('Developer.id'))->all()->first();

            $notif_count = TableRegistry::getTableLocator()->get('Notifications')->find(
                'all',
                [
                    'conditions' => ['developer_id' => (int) isset($current_developer) ? $current_developer['id'] : null],
                ]
            )->count();
            $this->set('current_developer', $current_developer);
            $this->set('developer_signed_in', true);

            $read_only = false;
            if ($this->request->getSession()->read('read_only')) {
                $read_only = true;
            }
            $this->set('read_only', $read_only);
        } else {
            $this->set('developer_signed_in', false);
            $this->set('read_only', true);
            $this->checkAccess();
        }
        $this->set('notif_count', $notif_count);
        $this->set('js_files', $this->js_files);
        $this->set('css_files', $this->css_files);
        $this->set('baseURL', Router::url('/', true));
    }

    protected function checkAccess(): ?Response
    {
        $controller = $this->request->controller;
        $action = $this->request->getParam('action');

        if (in_array($controller, $this->whitelist)) {
            return null;
        }
        if (isset($this->whitelist[$controller])
            && in_array($action, $this->whitelist[$controller])
        ) {
            return null;
        }
        $flash_class = 'alert';
        $this->Flash->default(
            'You need to be signed in to do this',
            ['params' => ['class' => $flash_class]]
        );

        // save the return url
        $ret_url = Router::url($this->request->getRequestTarget(), true);
        $this->request->getSession()->write('last_page', $ret_url);

        return $this->redirect('/');
    }

    protected function checkReadonlyAccess(): void
    {
        $controller = $this->request->controller;
        $action = $this->request->getParam('action');
        $read_only = $this->request->getSession()->read('read_only');

        // If developer has commit access on phpmyadmin/phpmyadmin
        if (! $read_only) {
            return;
        }

        if (in_array($controller, $this->readonly_whitelist)) {
            return;
        }
        if (isset($this->readonly_whitelist[$controller])
            && in_array($action, $this->readonly_whitelist[$controller])
        ) {
            return;
        }

        $this->request->getSession()->destroy();
        $this->request->getSession()->write('last_page', '');

        $flash_class = 'alert';
        $this->Flash->default(
            'You need to have commit access on phpmyadmin/phpmyadmin '
            . 'repository on Github.com to do this',
            [
                'params' => ['class' => $flash_class],
            ]
        );

        $this->redirect('/');
    }
}
