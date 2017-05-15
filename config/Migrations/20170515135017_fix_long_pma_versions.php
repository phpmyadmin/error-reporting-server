<?php

use Phinx\Migration\AbstractMigration;
use Cake\ORM\TableRegistry;
use Cake\Log\Log;

class FixLongPmaVersions extends AbstractMigration
{

    /**
     * This migration strips the phpMyAdmin version of Debian,
     * Ubuntu and other suffixes in version strings
     *
     */
    public function up()
    {
        // Strip phpMyAdmin versions in `reports` table
        $count = $this->_sanitizeVersionsInTable('reports');
        Log::debug($count . ' reports updated');

        // Strip phpMyAdmin versions in `incidents` table
        $count = $this->_sanitizeVersionsInTable('incidents');
        Log::debug($count . ' incidents updated');
    }

    public function down()
    {
        // Once applied, this migration can't be reversed
        // but the migration itself is idempotent
    }

    private function _sanitizeVersionsInTable($table)
    {
        $rows = $this->fetchAll(
            'SELECT `id`, `pma_version` FROM `' . $table . '`',
            PDO::FETCH_ASSOC
        );
        $count = 0;
        foreach ($rows as $row) {
            $strippedVersionString = TableRegistry::get('Incidents')->getStrippedPmaVersion(
                $row['pma_version']
            );

            $this->execute('UPDATE ' . $table . ' SET `pma_version` = \''
                . $strippedVersionString . '\' WHERE `id` = \''
                . $row['id'] . '\''
            );
            if ($strippedVersionString !== $row['pma_version']) {
                ++$count;
            }
        }

        return $count;
    }
}
