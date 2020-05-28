<?php

use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use Phinx\Migration\AbstractMigration;

class FixLongPmaVersions extends AbstractMigration
{
    /**
     * This migration strips the phpMyAdmin version of Debian,
     * Ubuntu and other suffixes in version strings
     */
    public function up(): void
    {
        // Strip phpMyAdmin versions in `reports` table
        $count = $this->_sanitizeVersionsInTable('reports');
        Log::debug($count . ' reports updated');

        // Strip phpMyAdmin versions in `incidents` table
        $count = $this->_sanitizeVersionsInTable('incidents');
        Log::debug($count . ' incidents updated');
    }

    public function down(): void
    {
        // Once applied, this migration can't be reversed
        // but the migration itself is idempotent
    }

    private function _sanitizeVersionsInTable(string $table): int
    {
        $sql = 'SELECT `id`, `pma_version` FROM `' . $table . '`';
        $count = 0;
        $incidentsTable = TableRegistry::getTableLocator()->get('Incidents');
        $result = $this->query($sql);

        while ($row = $result->fetch()) {
            $strippedVersionString
                = $incidentsTable->getStrippedPmaVersion(
                    $row['pma_version']
                );

            if ($strippedVersionString === $row['pma_version']) {
                continue;
            }

            $this->execute('UPDATE ' . $table . ' SET `pma_version` = \''
                . $strippedVersionString . '\' WHERE `id` = \''
                . $row['id'] . '\'');

            ++$count;
        }

        return $count;
    }
}
