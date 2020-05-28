<?php

use Phinx\Migration\AbstractMigration;

class AddDevReportIndexToNotifications extends AbstractMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-change-method
     */
    public function change(): void
    {
        $table = $this->table('notifications');
        $table->addIndex([
            'developer_id',
            'report_id',
        ]);
        $table->update();
    }
}
