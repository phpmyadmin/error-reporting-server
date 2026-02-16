<?php

use Migrations\BaseMigration;

class AddReportIdIndexToIncidents extends BaseMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-change-method
     */
    public function change(): void
    {
        $table = $this->table('incidents');
        $table->addIndex(
            ['report_id']
        );
        $table->update();
    }
}
