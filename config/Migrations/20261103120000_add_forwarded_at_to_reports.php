<?php

declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Records when a report was forwarded to the tracker.
 *
 * The forwarding used to run inside the request that stored the report, so a
 * slow tracker delayed the answer and the notification mail. A command does it
 * now: this column is what it reads to know what is left to send, and what an
 * operator can query to see a backlog.
 */
class AddForwardedAtToReports extends BaseMigration
{
    public function up(): void
    {
        $this->table('reports')
            ->addColumn('forwarded_at', 'datetime', [
                'null' => true,
                'default' => null,
                'after' => 'exception_type',
                'comment' => 'When the report was forwarded to the tracker, NULL when it was not',
            ])
            ->addIndex(['forwarded_at'], ['name' => 'forwarded_at'])
            ->update();
    }

    public function down(): void
    {
        $this->table('reports')
            ->removeIndexByName('forwarded_at')
            ->removeColumn('forwarded_at')
            ->update();
    }
}
