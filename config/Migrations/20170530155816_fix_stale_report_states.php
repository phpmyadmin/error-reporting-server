<?php

use Cake\Log\Log;
use Migrations\AbstractMigration;

class FixStaleReportStates extends AbstractMigration
{
    /**
     * This migration changes the old report statuses to more
     * Github-related status
     */
    public function up(): void
    {
        $this->_migrateBasedOnGithubLinked();
        $this->_migrateToNewStatus();
        $this->_migrateDuplicateReports();
    }

    private function _migrateBasedOnGithubLinked(): void
    {
        $sql = 'UPDATE `reports` SET `status` = \'forwarded\''
            . ' WHERE `sourceforge_bug_id` IS NOT NULL AND `status` <> \'fixed\'';
        $rowsAffected = $this->execute($sql);

        Log::debug(
            $rowsAffected . ' reports are linked to a'
                . ' Github issue.'
                . ' These have been marked to have a \'forwarded\' status.'
        );
    }

    private function _migrateToNewStatus(): void
    {
        $statusMap = [
            'fixed' => 'resolved',
            'open' => 'new',
            'out-of-date' => 'invalid',
            'works-for-me' => 'invalid',
            'wontfix' => 'invalid',
        ];

        foreach ($statusMap as $oldStatus => $newStatus) {
            $sql = 'UPDATE `reports` SET `status` = \'' . $newStatus . '\''
                . ' WHERE `status` = \'' . $oldStatus . '\'';
            $rowsAffected = $this->execute($sql);

            Log::debug(
                $rowsAffected . ' reports with \'' . $oldStatus . '\''
                    . ' were mapped to \'' . $newStatus . '\'.'
            );
        }
    }

    private function _migrateDuplicateReports(): void
    {
        // Find the original reports and set the status
        // of their duplicate reports same as their status
        $sql = 'SELECT `id`, `status` FROM `reports` WHERE'
            . ' `related_to` IS NULL';
        $origCount = 0;
        $result = $this->query($sql);
        $rowsAffected = 0;

        while ($row = $result->fetch()) {
            $update_sql = 'UPDATE `reports` SET `status` = \''
                . $row['status'] . '\' WHERE `id` = \''
                . $row['id'] . '\'';

            $rowsAffected += $this->execute($update_sql);
            $origCount++;
        }

        Log::debug(
            $rowsAffected . ' duplicate reports were mapped to '
                . ' same status as their ' . $origCount . ' original reports'
        );
    }
}
