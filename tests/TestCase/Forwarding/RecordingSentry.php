<?php

/**
 * phpMyAdmin Error reporting server
 * Copyright (c) phpMyAdmin project (https://www.phpmyadmin.net/)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) phpMyAdmin project (https://www.phpmyadmin.net/)
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 *
 * @see      https://www.phpmyadmin.net/
 */

namespace App\Test\TestCase\Forwarding;

use App\Forwarding\Sentry;

use function json_encode;

/**
 * A Sentry that records what it would have sent, instead of sending it.
 */
class RecordingSentry extends Sentry
{
    /** @var array<int, array<int, mixed>> */
    public static array $envelopes = [];

    /** The answer the fake tracker gives, when a test sets one. */
    public static array|null $answer = null;

    /**
     * @param array<int, array<int, mixed>> $items
     * @param array<string, mixed>          $sentryConfig
     *
     * @return array<int, int|string>
     */
    protected static function postEnvelope(array $items, string $eventId, array $sentryConfig): array
    {
        foreach ($items as [$type, $payload]) {
            self::$envelopes[] = [$payload, $type, $eventId];
        }

        return [200, json_encode(['id' => $eventId === '' ? 'feedback' : $eventId])];
    }
}
