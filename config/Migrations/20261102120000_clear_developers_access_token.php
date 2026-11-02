<?php

use Migrations\BaseMigration;

/**
 * Clear the GitHub access tokens that were stored in plain text.
 *
 * The application only keeps the token in the session, so the column values are
 * no longer needed.
 */
class ClearDevelopersAccessToken extends BaseMigration
{
    public function up(): void
    {
        $this->getQueryBuilder('update')
            ->update('developers')
            ->set(['access_token' => null])
            ->execute();
    }

    public function down(): void
    {
        // The tokens cannot be restored: they were stored in plain text and must
        // not be written back.
    }
}
