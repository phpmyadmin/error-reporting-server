<?php

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
use function date;
use function time;

/**
 * Clean old Notifications shell.
 */
class CleanOldNotifsShell extends Shell
{
    public function initialize(): void
    {
        parent::initialize();
        $this->loadModel('Notifications');
    }

    public function main(): void
    {
        $XTime = time() - 60 * 24 * 3600;
        $conditions = ['Notifications.created <' => date('Y-m-d H:i:s', $XTime)];

        if ($this->Notifications->find('all', ['conditions' => $conditions])->count() === 0) {
            // Check if there are any notifications to delete
            Log::write(
                'info',
                'No notifications found for deleting!',
                'cron_jobs'
            );
        } elseif ($this->Notifications->deleteAll($conditions)) {
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
