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

namespace App\Test\TestCase\View\Helper;

use App\View\Helper\IncidentsHelper;
use Cake\TestSuite\TestCase;
use Cake\View\View;

use function json_encode;

/**
 * The stack levels of a report are fields of an unauthenticated request.
 */
class IncidentsHelperTest extends TestCase
{
    protected string $payload = '<script>alert("xss")</script>';

    public function testTheStackLevelInfoIsEscaped(): void
    {
        $helper = new IncidentsHelper(new View());

        $incident = [
            'exception_type' => false,
            'stacktrace' => json_encode([
                [
                    'filename' => $this->payload,
                    'scriptname' => $this->payload,
                    'line' => '1',
                    'func' => $this->payload,
                    'column' => '1',
                ],
            ]),
        ];

        $html = $helper->getStacktrace($incident, 'well');

        $this->assertStringNotContainsString($this->payload, $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testThePhpStackLevelInfoIsEscaped(): void
    {
        $helper = new IncidentsHelper(new View());

        $incident = [
            'exception_type' => true,
            'stacktrace' => json_encode([
                [
                    'file' => $this->payload,
                    'line' => '1',
                    'function' => $this->payload,
                    'class' => $this->payload,
                    'args' => [$this->payload],
                ],
            ]),
        ];

        $html = $helper->getStacktrace($incident, 'well');

        $this->assertStringNotContainsString($this->payload, $html);
    }
}
