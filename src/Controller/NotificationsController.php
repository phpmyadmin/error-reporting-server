<?php

/* vim: set expandtab sw=4 ts=4 sts=4: */
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

use Cake\Event\Event;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;

/**
 * Notifications Controller.
 */
class NotificationsController extends AppController
{
    public $components = [
        'RequestHandler',
        'OrderSearch',
    ];

    public $helpers = [
        'Html',
        'Form',
        'Reports',
    ];

    public $uses = [
        'Notification',
        'Developer',
        'Report',
    ];

    public function beforeFilter(Event $event)
    {
        if ($this->request->getParam('action') != 'clean_old_notifs') {
            parent::beforeFilter($event);
        }
    }

    public function index()
    {
        // no need to do anything here. Just render the view.
    }

    public function data_tables()
    {
        $current_developer = TableRegistry::get('Developers')->
                    findById($this->request->session()->read('Developer.id'))->all()->first();

        $aColumns = [
            'report_id' => 'Reports.id',
            'error_message' => 'Reports.error_message',
            'error_name' => 'Reports.error_name',
            'pma_version' => 'Reports.pma_version',
            'exception_type' => 'Reports.exception_type',
            'created_time' => 'Notifications.created',
        ];

        $orderConditions = $this->OrderSearch->getOrder($aColumns);
        $searchConditions = $this->OrderSearch->getSearchConditions($aColumns);

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
        $pagedParams['limit'] = intval($this->request->query('iDisplayLength'));
        $pagedParams['offset'] = intval($this->request->query('iDisplayStart'));

        $rows = $this->Notifications->find('all', $pagedParams);
        $totalFiltered = $this->Notifications->find(
            'all',
            [
                'conditions' => [
                    'developer_id' => $current_developer['id'],
                ],
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
                        'controller' => 'reports',
                        'action' => 'view',
                        $row['report_id'],
                    ]
                )
                . '">'
                . $row['report_id']
                . '</a>';
            $tmp_row[2] = $row['error_name'];
            $tmp_row[3] = $row['error_message'];
            $tmp_row[4] = $row['pma_version'];
            $tmp_row[5] = ($row['exception_type']) ? ('php') : ('js');
            $tmp_row[6] = $row['created_time'];
            array_push($dispRows, $tmp_row);
        }

        $response = [
            'iTotalDisplayRecords' => $totalFiltered,
            'iTotalRecords' => $this->Notifications->find('all', $params)->count(),
            'sEcho' => intval($this->request->query('sEcho')),
            'aaData' => $dispRows,
        ];
        $this->autoRender = false;
        $this->response->body(json_encode($response));

        return $this->response;
    }

    /**
     * To carry out mass actions on Notifications.
     * Currently it deletes them (marks them "read").
     * Can be Extended for other mass operations as well.
     * Expects an array of Notification Ids as a POST parameter.
     * @return void
     */
    public function mass_action()
    {
        $msg = 'Selected Notifications have been marked \'Read\'!';
        $flash_class = 'alert alert-success';

        if ($this->request->getData('mark_all')) {
            // Delete all notifications for this Developer
            $this->Notifications->deleteAll(
                ['developer_id' => $this->request->session()->read('Developer.id')]
            );

            $msg = 'All your notifications have been marked \'Read\'';
        } else {
            foreach ($this->request->data['notifs'] as $notif_id) {
                if (! $this->Notifications->delete($this->Notifications->get(intval($notif_id)))) {
                    $msg = '<b>ERROR</b>: There was some problem in deleting Notification(ID:'
                        . $notif_id
                        . ')!';
                    $flash_class = 'alert alert-error';
                    break;
                }
            }
        }
        $this->Flash->default(
            $msg,
            ['params' => ['class' => $flash_class]]
        );
        $this->redirect('/notifications/');
    }
}
