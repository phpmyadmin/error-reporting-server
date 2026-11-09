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

namespace App\Forwarding;

use Cake\Core\Exception\CakeException;

/**
 * The tracker asked to be left alone for a while.
 *
 * A 429 with a Retry-After is not a refusal of the payload: asking again now
 * only makes the wait longer, so the run gives up and the next one asks later.
 */
class TrackerThrottledException extends CakeException
{
    /**
     * @param string $message    The message
     * @param int    $retryAfter Seconds the tracker asked to wait
     */
    public function __construct(string $message, private int $retryAfter = 0)
    {
        parent::__construct($message);
    }

    /**
     * Seconds the tracker asked to wait, 0 when it did not say.
     */
    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }
}
