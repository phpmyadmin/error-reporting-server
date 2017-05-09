<?php

/* vim: set expandtab sw=4 ts=4 sts=4: */
/**
 * Clean Old Notifications Shell.
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

namespace App\Shell;

use Cake\Console\Shell;
use Cake\Log\Log;

/**
 * Clean old Notifications shell.
 */
class CleanOldNotifsShell extends Shell
{
    public function initialize()
    {
        parent::initialize();
        $this->loadModel('Notifications');
    }

    public function main()
    {
        $XTime = time() - 60 * 24 * 3600;
        $conditions = array('Notifications.created <' => date('Y-m-d H:i:s', $XTime));

        if ($this->Notifications->find('all', array('conditions' => $conditions))->count() === 0) {
            // Check if there are any notifications to delete
            Log::write(
                'info',
                'No notifications found for deleting!',
                'cron_jobs'
            );
        } elseif (!$this->Notifications->deleteAll($conditions)) {
            // Try deleting the matched records
            Log::write(
                'info',
                'Old notifications deleted successfully!',
                'cron_jobs'
            );
        } else {
            // If NOT successful, print out an error message
            Log::write(
                'error',
                'Deleting old notifications failed!',
                'cron_jobs'
            );
        }
    }
}
