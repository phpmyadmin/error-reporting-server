<?php

/**
 * Notifications controller handling notification creation and rendering.
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
use App\Model\Table\ReportsTable;
use Cake\Event\EventInterface;
use Cake\Http\Response;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;

use function array_push;
use function intval;
use function json_encode;

/**
 * Notifications Controller.
 */
class NotificationsController extends AppController
{
    protected NotificationsTable $Notifications;
    protected DevelopersTable $Developers;
    protected ReportsTable $Reports;

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
        $this->loadComponent('OrderSearch');
        $this->viewBuilder()->setHelpers([
            'Html',
            'Form',
            'Reports',
        ]);
        $this->Notifications = $this->fetchTable('Notifications');
        $this->Developers = $this->fetchTable('Developers');
        $this->Reports = $this->fetchTable('Reports');
    }

    public function beforeFilter(EventInterface $event)
    {
        if ($this->request->getParam('action') === 'clean_old_notifs') {
            return;
        }

        parent::beforeFilter($event);
    }

    public function index(): void
    {
        // no need to do anything here. Just render the view.
    }

    public function data_tables(): Response
    {
        $devId = $this->request->getSession()->read('Developer.id');
        $current_developer = TableRegistry::getTableLocator()->get('Developers')->
                    findById($devId)->all()->first();

        $aColumns = [
            'report_id' => 'Reports.id',
            'error_message' => 'Reports.error_message',
            'error_name' => 'Reports.error_name',
            'pma_version' => 'Reports.pma_version',
            'exception_type' => 'Reports.exception_type',
            'created_time' => 'Notifications.created',
        ];

        $orderConditions = $this->OrderSearch->getOrder($aColumns, $this->request);
        $searchConditions = $this->OrderSearch->getSearchConditions($aColumns, $this->request);

        $aColumns['id'] = 'Notifications.id';
        $params = [
            'contain' => 'Reports',
            'fields' => $aColumns,
            'conditions' => [
                'AND' => [
                    ['Notifications.developer_id ' => $current_developer['id']],
                    $searchConditions,
                ],
            ],
            'order' => $orderConditions,
        ];

        $pagedParams = $params;
        $pagedParams['limit'] = intval($this->request->getQuery('iDisplayLength'));
        $pagedParams['offset'] = intval($this->request->getQuery('iDisplayStart'));

        $rows = $this->Notifications->find(
            'all',
            contain: $pagedParams['contain'],
            fields: $pagedParams['fields'],
            conditions: $pagedParams['conditions'],
            order: $pagedParams['order'],
            limit: $pagedParams['limit'],
            offset: $pagedParams['offset'],
        );
        $totalFiltered = $this->Notifications->find(
            'all',
            conditions: [
                'developer_id' => $current_developer['id'],
            ]
        )->count();

        // Make the display rows array
        $dispRows = [];
        $tmp_row = [];
        foreach ($rows as $row) {
            $tmp_row[0] = '<input type="checkbox" name="notifs[]" value="'
                . $row['id']
                . '"/>';
            $tmp_row[1] = '<a href="'
                . Router::url(
                    [
                        '_name' => 'reports:view',
                        'id' => $row['report_id'],
                    ]
                )
                . '">'
                . $row['report_id']
                . '</a>';
            $tmp_row[2] = $row['error_name'];
            $tmp_row[3] = $row['error_message'];
            $tmp_row[4] = $row['pma_version'];
            $tmp_row[5] = $row['exception_type'] ? 'php' : 'js';
            $tmp_row[6] = $row['created_time'];
            array_push($dispRows, $tmp_row);
        }

        $response = [
            'iTotalDisplayRecords' => $totalFiltered,
            'iTotalRecords' => $this->Notifications->find(
                'all',
                contain: $params['contain'],
                fields: $params['fields'],
                conditions: $params['conditions'],
                order: $params['order'],
            )->count(),
            'sEcho' => intval($this->request->getQuery('sEcho')),
            'aaData' => $dispRows,
        ];
        $this->disableAutoRender();

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode($response));
    }

    /**
     * To carry out mass actions on Notifications.
     * Currently it deletes them (marks them "read").
     * Can be Extended for other mass operations as well.
     * Expects an array of Notification Ids as a POST parameter.
     */
    public function mass_action(): Response
    {
        $msg = 'Selected Notifications have been marked \'Read\'!';
        $flash_class = 'alert alert-success';

        if ($this->request->getData('mark_all')) {
            // Delete all notifications for this Developer
            $this->Notifications->deleteAll(
                ['developer_id' => $this->request->getSession()->read('Developer.id')]
            );

            $msg = 'All your notifications have been marked \'Read\'';
        } else {
            foreach ($this->request->getData('notifs') as $notif_id) {
                if (! $this->Notifications->delete($this->Notifications->get(intval($notif_id)))) {
                    $msg = '<b>ERROR</b>: There was some problem in deleting Notification(ID:'
                        . $notif_id
                        . ')!';
                    $flash_class = 'alert alert-error';
                    break;
                }
            }
        }

        $this->Flash->set(
            $msg,
            ['params' => ['class' => $flash_class]]
        );

        return $this->redirect('/notifications/');
    }
}
