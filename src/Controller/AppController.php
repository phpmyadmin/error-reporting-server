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

use App\Model\Table\DevelopersTable;
use App\Model\Table\NotificationsTable;
use Cake\Controller\Controller;
use Cake\Event\EventInterface;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;

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
    protected NotificationsTable $Notifications;
    protected DevelopersTable $Developers;

    /** @var string[] */
    public array $css_files = [
        'jquery.dataTables',
        'jquery.dataTables_themeroller',
        'bootstrap.min',
        'bootstrap-responsive.min',
        'shCore',
        'shThemeDefault',
        'custom',
    ];

    /** @var string[] */
    public array $js_files = [
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
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('Flash');
        $this->Notifications = $this->fetchTable('Notifications');
        $this->Developers = $this->fetchTable('Developers');

        $this->set('js_files', $this->js_files);
        $this->set('css_files', $this->css_files);
        $this->set('baseURL', Router::url('/', true));
    }

    public function beforeFilter(EventInterface $event)
    {
        $controllerName = $this->request->getParam('controller');
        $this->set('current_controller', $controllerName);

        // Attributes where set in the Authentication Middleware
        $currentDeveloper = $this->request->getAttribute('current_developer');

        $notificationCount = 0;

        // The user is logged in
        if ($currentDeveloper !== null) {
            $this->set('read_only', $this->request->getSession()->read('read_only'));
            $notificationCount = TableRegistry::getTableLocator()->get('Notifications')->find(
                'all',
                conditions: ['developer_id' => $currentDeveloper['id']]
            )->count();
        } else {
            $this->set('read_only', true);
        }

        $this->set('notif_count', $notificationCount);
    }
}
