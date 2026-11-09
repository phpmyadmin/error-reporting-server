<?php

declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Index the two lookups the application makes on reports.
 *
 * Every report submission looks for the report of a nearby error before it
 * creates one, on location, line and version; without an index each error of
 * each submission scans the whole table. The related reports of a report are
 * read on the report list and on every notification mail, and that scan is a
 * second full pass.
 */
class AddReportLookupIndexes extends BaseMigration
{
    public function up(): void
    {
        // location is a TEXT column: the index covers its first characters,
        // which is what a path comparison needs.
        $this->table('reports')
            ->addIndex(['location', 'linenumber', 'pma_version'], [
                'name' => 'report_lookup',
                'limit' => ['location' => 255],
            ])
            ->addIndex(['related_to'], ['name' => 'related_to'])
            ->update();
    }

    public function down(): void
    {
        $this->table('reports')
            ->removeIndexByName('report_lookup')
            ->removeIndexByName('related_to')
            ->update();
    }
}
