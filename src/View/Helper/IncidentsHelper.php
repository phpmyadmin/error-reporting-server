<?php

/**
 * Incidents View helper.
 *
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

namespace App\View\Helper;

use function count;
use function gettype;
use function htmlspecialchars;
use function implode;
use function is_string;
use function json_decode;
use function strlen;
use function substr;

/**
 * Incidents View helper.
 */
class IncidentsHelper extends AppHelper
{
    /**
     * @param mixed $incidents
     * @return string comma separated list
     */
    public function createIncidentsLinks($incidents): string
    {
        $links = [];
        foreach ($incidents as $incident) {
            $links[] = $this->linkToIncident($incident);
        }

        return implode(', ', $links);
    }

    /**
     * @param mixed $incident The incident
     * @return string HTML <a> code
     */
    public function linkToIncident($incident): string
    {
        $incidentId = $incident['id'];

        return '<a href="/' . BASE_DIR . 'incidents/view/' . $incidentId . '">#' . $incidentId . '</a>';
    }

    /**
     * @param mixed $incidents The incidents
     * @return string HTML code
     */
    public function incidentsDescriptions($incidents): string
    {
        $descriptions = '';
        foreach ($incidents as $incident) {
            $descriptions .= '<span>Incident ';
            $descriptions .= $this->linkToIncident($incident);
            $descriptions .= ':</span>';
            $descriptions .= '<pre>';
            $descriptions .= htmlspecialchars($incident['steps']);
            $descriptions .= '</pre>';
        }

        return $descriptions;
    }

    /**
     * @param mixed  $incident The incident
     * @param stirng $divClass A class for the div
     * @return string HTML code
     */
    public function getStacktrace($incident, string $divClass): string
    {
        $html = '';
        $html .= '<div class="' . $divClass . '">';

        if (is_string($incident['stacktrace'])) {
            $incident['stacktrace'] =
                    json_decode($incident['stacktrace'], true);
        }

        foreach ($incident['stacktrace'] as $level) {
            $exception_type = ($incident['exception_type'] ? 'php' : 'js');
            $html .= $this->getStackLevelInfo($level, $exception_type);
            $html .= "<pre class='brush: "
                . $exception_type
                . '; tab-size: 2';
            if (isset($level['line']) && $level['line']) {
                if ($incident['exception_type']) {
                    $html .= '; first-line: ' . (int) $level['line'];
                } elseif ((int) $level['line'] > 5) {
                    $html .= '; first-line: ' . ((int) $level['line'] - 5);
                }
                $html .= '; highlight: [' . (int) $level['line'] . ']';
            }
            $html .= "'>";

            if ($exception_type === 'js') {
                if (isset($level['context'])) {
                    $html .= htmlspecialchars(implode("\n", $level['context']));
                }
            } else {
                $html .= htmlspecialchars($level['function']);
                $html .= '(';
                $argList = '';
                if (count($level['args']) > 0) {
                    foreach ($level['args'] as $arg) {
                        $argList .= "\n"
                            . gettype($arg)
                            . ' => '
                            . $arg;
                        $argList .= ',';
                    }
                    $argList = substr($argList, 0, (strlen($argList) - 1));
                    $argList .= "\n";
                }
                $html .= htmlspecialchars($argList);
                $html .= ')';
            }
            $html .= '</pre>';
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * @param mixed  $level          The level
     * @param string $exception_type The execption type (php/js)
     * @return string HTML code
     */
    protected function getStackLevelInfo($level, string $exception_type = 'js'): string
    {
        $html = '<span>';
        if ($exception_type === 'js') {
            $elements = [
                'filename',
                'scriptname',
                'line',
                'func',
                'column',
            ];
        } else {
            $elements = [
                'file',
                'line',
                'function',
                'class',
            ];
        }
        foreach ($elements as $element) {
            if (! isset($level[$element])) {
                continue;
            }

            $html .= $element . ': ' . $level[$element] . '; ';
        }
        $html .= '</span>';

        return $html;
    }
}
