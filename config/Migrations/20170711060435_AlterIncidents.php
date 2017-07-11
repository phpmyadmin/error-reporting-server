<?php
use Migrations\AbstractMigration;

class AlterIncidents extends AbstractMigration
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
        $table->changeColumn('error_message', 'string', [
            'default' => null,
            'limit' => 200,
            'null' => true,
        ]);
        $table->update();
    }
}
