<?php use Cake\Routing\Router ?>
<h1>Reports</h1>
<form class="form-inline" id="state-form" style=""
        action=" <?= Router::url('/reports/mass_action/'); ?> "
        method="post">
    <table id="reports_table" data-ajax-url="<?=
            Router::url(
                [
                    'controller' => 'reports',
                    'action' => 'data_tables'
                ]
            );
    ?>">
        <thead>
            <tr>
                <th>Select</th>
                <th>ID</th>
                <th>Exception Name</th>
                <th>Message</th>
                <th>Location</th>
                <th>PMA Version</th>
                <th>Status</th>
                <th>Exception Type</th>
                <th>Incident count</th>
            </tr>
            <tr>
                <th></th>
                <th>
                    <input id="id_filter" style="width:100%; margin-left:-10px"
                        type="number"/>
                </th>
                <th>
                    <select id="error_name_filter" style="width:100%">
                        <option></option>
                        <?php foreach ($distinct_error_names as $id => $name): ?>
                            <?= "<option value='$name'>$name</option>"; ?>
                        <?php endforeach; ?>
                    </select>
                </th>
                <th></th>
                <th>
                    <select id="location_filter" style="width:100%">
                        <option></option>
                        <?php foreach ($distinct_locations as $id => $location): ?>
                            <?= "<option value='$location'>$location</option>"; ?>
                        <?php endforeach; ?>
                    </select>
                </th>
                <th>
                    <select id="pma_version_filter" style="width:100%">
                        <option></option>
                        <?php foreach ($distinct_versions as $id => $version): ?>
                            <?= "<option value='$version'>$version</option>"; ?>
                        <?php endforeach; ?>
                    </select>
                </th>
                <th>
                    <select id="status_filter" style="width:100%">
                        <option></option>
                        <?php foreach ($distinct_statuses as $id => $status): ?>
                            <?= "<option value='$status'>$statuses[$status]</option>"; ?>
                        <?php endforeach; ?>
                    </select>
                </th>
                <th>
                    <select id="exception_type_filter" style="width:100%">
                        <option></option>
                        <option value='0'>js</option>
                        <option value='1'>php</option>
                    </select>
                </th>
                <th>
                </th>
            </tr>
        </thead>

        <tbody>
        </tbody>
        <tfoot>
            <tr>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
            </tr>
        </tfoot>
    </table>

    <!-- Show this only if Developer has commit access -->
    <?php if (!$read_only): ?>
        <div style="margin:10px; clear:both;">
            <input type="checkbox" id="resultsForm_checkall"
                class="checkall_box" title="Check All"
                style="display:inline-block; margin:0;" />
            <label for="resultsForm_checkall" style="pointer:cursor; display: inline-block;">
                Check all
            </label>
            <span style="margin-left:2em">
                With <i>selected </i>Change state to:
            </span>
            <?=
                $this->Form->select(
                    'state',
                    $statuses,
                    array(
                        'empty' => false
                    )
                );
            ?>
            <input type="submit" value="Change" class="btn btn-primary" />
        </div>
    <?php endif; ?>
</form>
