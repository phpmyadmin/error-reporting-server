<?php

declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Records when an incident was forwarded to the tracker.
 *
 * An incident is one occurrence of an error: a report groups them. The
 * forwarding runs in a command, and it has to send every occurrence so that an
 * issue the tracker opened keeps showing that it still happens — this column is
 * what says which occurrence was already sent.
 */
class AddForwardedAtToIncidents extends BaseMigration
{
    public function up(): void
    {
        $this->table('incidents')
            ->addColumn('forwarded_at', 'datetime', [
                'null' => true,
                'default' => null,
                'after' => 'created',
                'comment' => 'When the incident was forwarded to the tracker, NULL when it was not',
            ])
            ->addIndex(['forwarded_at'], ['name' => 'forwarded_at'])
            ->update();
    }

    public function down(): void
    {
        $this->table('incidents')
            ->removeIndexByName('forwarded_at')
            ->removeColumn('forwarded_at')
            ->update();
    }
}
