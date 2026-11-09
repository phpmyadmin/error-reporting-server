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
 * The tracker could not be reached, and asking it again will not help.
 *
 * Raised once several attempts in a row failed to connect: a run that keeps
 * trying costs its whole timeout budget per event and never drains anything.
 */
class TrackerUnreachableException extends CakeException
{
}
