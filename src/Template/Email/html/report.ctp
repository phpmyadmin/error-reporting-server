<?php
/**
 * Report Notification Email Template
 *
 * phpMyAdmin Error reporting server
 * Copyright (c) phpMyAdmin project (https://www.phpmyadmin.net/)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) phpMyAdmin project (https://www.phpmyadmin.net/)
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 *
 * @see          https://www.phpmyadmin.net/
 */
?>
<?php use Cake\Routing\Router; ?>

<p>
	A new error report has been added on the phpMyAdmin's Error Reporting System.
</p>
<p>
	The details of the report are as follows:
</p>

<table cellspacing="0" style="border:1px solid #333">
    <tr>
        <td style="width:20%">Error Type</td>
        <td><?= $report['exception_type'] ? 'php' : 'js'; ?></td>
    </tr>
    <tr>
        <td>Error Name</td>
        <td><?= $report['error_name']; ?></td>
    </tr>
    <tr>
        <td>Error Message</td>
        <td><?= $report['error_message']; ?></td>
    </tr>
    <tr>
        <td>PMA Versions</td>
        <td>
            <?=
                $this->Reports->entriesFromIncidents(
                    $related_entries['pma_version'],
                    $pma_version_distinct_count,
                    'pma_version'
                );
            ?>
        </td>
    </tr>
    <tr>
        <td>PHP Versions</td>
        <td>
            <?=
                $this->Reports->entriesFromIncidents(
                    $related_entries['php_version'],
                    $php_version_distinct_count,
                    'php_version'
                );
            ?>
        </td>
    </tr>
    <tr>
        <td>Browsers</td>
        <td>
            <?=
                $this->Reports->entriesFromIncidents(
                    $related_entries['browser'],
                    $browser_distinct_count,
                    'browser'
                );
            ?>
        </td>
    </tr>
    <?php if ($incidents[0]['exception_type']): // php ?>
        <tr>
            <td>Location</td>
            <td><?= $report['location']; ?></td>
        </tr>
        <tr>
            <td>Line Number</td>
            <td><?= $report['linenumber']; ?></td>
        </tr>
    <?php else: ?>
        <tr>
            <td>Script Name</td>
            <td>
                <?=
                    $this->Reports->entriesFromIncidents(
                        $related_entries['script_name'],
                        $script_name_distinct_count,
                        'script_name'
                    );
                ?>
            </td>
        </tr>
    <?php endif; ?>
    <tr>
        <td>Configuration Storage</td>
        <td>
            <?=
                $this->Reports->entriesFromIncidents(
                    $related_entries['configuration_storage'],
                    $configuration_storage_distinct_count,
                    'configuration_storage'
                );
            ?>
        </td>
    </tr>
    <tr>
        <td>Server Software</td>
        <td>
            <?=
                $this->Reports->entriesFromIncidents(
                    $related_entries['server_software'],
                    $server_software_distinct_count,
                    'server_software'
                );
            ?>
        </td>
    </tr>
    <tr>
        <td>User OS</td>
        <td>
            <?=
                $this->Reports->entriesFromIncidents(
                    $related_entries['user_os'],
                    $user_os_distinct_count,
                    'user_os'
                );
            ?>
        </td>
    </tr>
    <tr>
        <td>Locale</td>
        <td>
            <?=
                $this->Reports->entriesFromIncidents(
                    $related_entries['locale'],
                    $locale_distinct_count,
                    'locale'
                );
            ?>
        </td>
    </tr>
    <tr>
        <td>Incident Count</td>
        <td><?= count($incidents) . ' incidents of this bug'; ?></td>
    </tr>
    <tr>
        <td>Submission Date</td>
        <td>
            <?= $report['created']; ?>
        </td>
    </tr>
</table>

<p>
	You can view the detailed report at
	<a href="<?= Router::url('/reports/view/' . $report['id'], true) ?>">
		#<?= $report['id'] ?></a>.
</p>