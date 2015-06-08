<?php
use Phinx\Migration\AbstractMigration;

class AddReportIdIndexToIncidents extends AbstractMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-change-method
     * @return void
     */
    public function change()
    {
        $table = $this->table('incidents');
        $table->addIndex(
        [
            'report_id',
        ]);
        $table->update();
    }
}
