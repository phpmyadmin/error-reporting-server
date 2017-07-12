<?php use Cake\Routing\Router; ?>
<div>
    <h1 style="display: inline">Error Report #<?= $report[0]['id'] ?>
            <small>[<?= $status[$report[0]['status']]; ?>]</small>
    </h1>
    <?php if (! $read_only) : ?>
        <a href="#" onclick="showStateForm(); return false">Change state</a>
    <?php endif; ?>
</div>
<br />

<?php if (! $read_only) : ?>
    <form class="form-inline" id="state-form" style="display: none"
            action="/<?= BASE_DIR ?>reports/change_state/<?= $report[0]['id']; ?>"
            method="post">
        <span>Change state to:</span>
        <?=
            $this->Form->select(
                    'state',
                    $status,
                    array(
                        'value' => $report[0]['status'],
                        'empty' => false
                    )
            );
        ?>
        <input type="submit" value="Change" class="btn btn-primary" />
    </form>
<?php endif; ?>

<?php if ($related_reports->isEmpty() && !$read_only): ?>
    <form class="form-inline" action="/<?= BASE_DIR ?>reports/mark_related_to/<?=
        $report[0]['id']; ?>" method="post">
        <span>Mark the same as:</span>
        <input type="number" min="1" name="related_to" />
        <input type="submit" value="Submit" class="btn btn-primary" />
    </form>
<?php elseif (! $related_reports->isEmpty()): ?>
    <p>
        This report has been marked the same as the following reports:
        (<?= $this->Reports->createReportsLinks($related_reports); ?>).
        <form class="form-inline" action="/<?= BASE_DIR ?>reports/unmark_related_to/<?= $report[0]['id']; ?>" method="post">
                <input type="submit" value="Remove from this group" class="btn btn-primary" />
        </form>
    </p>
<?php endif; ?>

<table cellspacing="0" class="table table-bordered error-report">
    <tr>
        <td>Error Type</td>
        <td><?= $report[0]['exception_type'] ? 'php' : 'js'; ?></td>
    </tr>
    <tr>
        <td>Error Name</td>
        <td><?= $report[0]['error_name']; ?></td>
    </tr>
    <tr>
        <td>Error Message</td>
        <td><?= $report[0]['error_message']; ?></td>
    </tr>
    <tr>
        <td>GitHub Issue</td>
        <td>
            <?php if ($report[0]['sourceforge_bug_id']) : ?>
                <?=
                    $this->Html->link(
                        '#' . $report[0]['sourceforge_bug_id'],
                        "https://github.com/$project_name/issues/"
                            . $report[0]['sourceforge_bug_id'],
                        ['target' => '_blank']
                    );
                ?>
                <?=
                    '<form action="'
                        . Router::url('/github/unlink_issue/', true) . $report[0]['id']
                        . '" method="GET" class="form-horizontal" style="margin-bottom:5px;"'
                        . ' onclick="return window.confirm(\'Are you sure you want to unlink this report?\');" >';
                ?>

                <?=
                    $this->Form->input('Unlink from issue', array(
                        'type' => 'submit',
                        'label' => false,
                        'class'=>'btn btn-primary'
                        )
                    )
                    . '</form>';
                ?>
            <?php elseif (! $read_only): ?>
                <table cellspacing="0" class="table table-bordered error-report"
                    style="width:300px; margin-bottom:5px;">
                    <tr>
                        <td style="min-width:130px;">
                        <?=
                            $this->Html->link(
                                'Create new issue',
                                '/github/create_issue/' . $report[0]['id'],
                                array(
                                    'class'=>'btn btn-primary'
                                )
                            );
                        ?>
                        </td>
                        <td style="min-width:130px;">
                            <?=
                                '<form action="'
                                    . Router::url('/', true) . 'github/link_issue/'
                                    . $report[0]['id'] . '" method="GET" '
                                    . 'class="form-horizontal" style="margin-bottom:5px;">';
                            ?>

                            <?=
                                $this->Form->input(
                                    'ticket_id',
                                    array(
                                        'placeholder' => 'Issue Number',
                                        'type' => 'text',
                                        'label' => false,
                                        'div' => true,
                                        'class' => 'input-large',
                                        'name' => 'ticket_id'
                                    )
                                );
                            ?>
                            <?=
                                $this->Form->input(
                                    'Link with existing Issue',
                                    array(
                                        'placeholder' => 'Ticket Number',
                                        'type' => 'submit',
                                        'label' => false,
                                        'div' => '',
                                        'class'=>'btn btn-primary'
                                    )
                                );
                            ?>
                            </form>
                        </td>
                    </tr>
                </table>
            <?php else: ?>
                This report is not linked to any Github issue.
            <?php endif; ?>
        </td>
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
            <td><?= $report[0]['location']; ?></td>
        </tr>
        <tr>
            <td>Line Number</td>
            <td><?= $report[0]['linenumber']; ?></td>
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
            <?= $report[0]['created']; ?> and it was last seen on
            <?= $incidents[0]['created']; ?>
        </td>
    </tr>
    <tr>
        <td>Incidents</td>
        <td>
            <?= $this->Incidents->createIncidentsLinks($incidents); ?>
        </td>
    </tr>
</table>

<?php if (! $read_only) : ?>
<h4>Stacktraces:</h4>
<?= $this->Reports->getStacktracesForIncidents($incidents_with_stacktrace); ?>
<?php endif; ?>

<?php if ($incidents_with_description->count() > 0): ?>
    <h4>Descriptions submitted by users:</h4>
    <?= $this->Incidents->incidentsDescriptions($incidents_with_description); ?>
<?php endif; ?>

<h4>Stats and Graphs</h4>
<span id="graphs"></span>

<script type="text/javascript">
    <?=
        $this->Reports->getChartArray(
            'chartArray',
            $columns,
            $related_entries
        );
    ?>

    window.onload = function () {
        chartArray.forEach(function(chart) {
            var span_id = 'graph_' + chart.name;
            var $span = $("<span class='span5'>").attr('id', span_id);
            $("#graphs").append($span);

            piechart(span_id, chart.title, chart.values, chart.labels);
        });
    };
</script>
