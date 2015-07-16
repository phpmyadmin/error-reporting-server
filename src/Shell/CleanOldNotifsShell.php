<?php

namespace App\Shell;

use Cake\Console\Shell;
use Cake\Log\Log;

class CleanOldNotifsShell extends Shell
{
    public function initialize()
    {
        parent::initialize();
        $this->loadModel('Notifications');
    }
    public function main()
    {
        $XTime = time() - 60*24*3600;
		$conditions = array('Notifications.created <' => date('Y-m-d H:i:s', $XTime));
		if (!$this->Notifications->deleteAll($conditions)) {
			Log::write(
				'error',
				'FAILED: Deleting older Notifications!!',
				'cron_jobs'
			);
		}
    }
}