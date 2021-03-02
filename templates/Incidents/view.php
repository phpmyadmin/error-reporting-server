<h1>Incident #<?= $incident['id']; ?>
    <small>[Report <?= $this->Reports->linkToReportFromIncident($incident); ?>]</small>
</h1>
<table cellspacing="0" class="table table-bordered error-report">
    <tr>
        <td>Error Type</td>
        <td>
            <?= $incident['exception_type'] ? 'php' : 'js'; ?>
        </td>
    </tr>
    <tr>
        <td>Error Name</td>
        <td>
            <?= $incident['error_name']; ?>
        </td>
    </tr>
    <tr>
        <td>Error Message</td>
        <td>
            <?= $incident['error_message']; ?>
        </td>
    </tr>
    <tr>
        <td>Submission Date</td>
        <td>
            <?= $incident['created']; ?>
        </td>
    </tr>
    <tr>
        <td>PMA Version</td>
        <td>
            <?= $incident['full_report']['pma_version']; ?>
        </td>
    </tr>
    <tr>
        <td>PHP Version</td>
        <td>
            <?= $incident['full_report']['php_version']; ?>
        </td>
    </tr>
    <tr>
        <td>Browser</td>
        <td>
            <?=
                $incident['full_report']['browser_name'] . ' '
                    . $incident['full_report']['browser_version'];
            ?>
        </td>
    </tr>
    <tr>
        <td>Useragent</td>
        <td>
            <?= $incident['full_report']['user_agent_string']; ?>
        </td>
    </tr>
    <tr>
        <td>Server Software</td>
        <td>
            <?= $incident['full_report']['server_software']; ?>
        </td>
    </tr>
    <tr>
        <td>User OS</td>
        <td>
            <?= $incident['user_os']; ?>
        </td>
    </tr>
    <tr>
        <td>Locale</td>
        <td>
            <?= $incident['full_report']['locale']; ?>
        </td>
    </tr>
    <tr>
        <td>Script name</td>
        <td>
            <?= $incident['script_name']; ?>
        </td>
    </tr>
    <tr>
        <td>URI</td>
        <td>
            <?php if ($incident['exception_type']): ?>
                <?= 'NA'; ?>
            <?php else: ?>
                <?= $incident['full_report']['exception']['uri']; ?>
            <?php endif; ?>
        </td>
    </tr>
    <tr>
        <td>Configuration storage enabled</td>
        <td>
            <?= $incident['full_report']['configuration_storage']; ?>
        </td>
    </tr>
</table>

<?php if (! empty($incident['steps'])): ?>
    <h4>Description submited by user:</h4>
    <pre>
        <?= nl2br($incident['steps']); ?>
    </pre>
<?php endif; ?>

<h4>Stacktrace:</h4>
<?= $this->Incidents->getStacktrace($incident, 'well'); ?>

<?php if (isset($incident['full_report']['microhistory'])): ?>
<h4>Microhistory:</h4>
<pre>
    <?php if (($incident['exception_type'])): ?>
        <?= 'NA'; ?>
    <?php else: ?>
        <?=
            json_encode(
                $incident['full_report']['microhistory'],
                JSON_PRETTY_PRINT
            );
        ?>
    <?php endif; ?>
</pre>
<?php endif; ?>
