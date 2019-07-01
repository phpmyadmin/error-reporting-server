<?php

/* vim: set expandtab sw=4 ts=4 sts=4: */
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

use App\View\Helper\AppHelper;
use Cake\View\View;

/**
 * Incidents View helper.
 */
class IncidentsHelper extends AppHelper
{
    public function __construct(View $view, $settings = [])
    {
        parent::__construct($view, $settings);
    }

    public function createIncidentsLinks($incidents)
    {
        $links = [];
        foreach ($incidents as $incident) {
            $links[] = $this->linkToIncident($incident);
        }
        $string = implode(', ', $links);

        return $string;
    }

    public function linkToIncident($incident)
    {
        $incidentId = $incident['id'];
        $link = "<a href='/" . BASE_DIR . "incidents/view/$incidentId'>#$incidentId</a>";

        return $link;
    }

    public function incidentsDescriptions($incidents)
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

    public function getStacktrace($incident, $divClass)
    {
        $html = '';
        $html .= "<div class='$divClass'>";

        if (is_string($incident['stacktrace'])) {
            $incident['stacktrace'] =
                    json_decode($incident['stacktrace'], true);
        }

        foreach ($incident['stacktrace'] as $level) {
            $exception_type = (($incident['exception_type']) ? ('php') : ('js'));
            $html .= $this->_getStackLevelInfo($level, $exception_type);
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

            if ($exception_type == 'js') {
                if (isset($level['context'])) {
                    $html .= htmlspecialchars(join("\n", $level['context']));
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

    protected function _getStackLevelInfo($level, $exception_type = 'js')
    {
        $html = '<span>';
        if ($exception_type == 'js') {
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
            if (isset($level[$element])) {
                $html .= "$element: " . $level[$element] . '; ';
            }
        }
        $html .= '</span>';

        return $html;
    }
}
