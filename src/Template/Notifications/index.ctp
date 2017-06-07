<?php use Cake\Routing\Router; ?>
<div class="notifications index">
    <h2><?= __('Notifications'); ?></h2>
    <form name="notif_mass_action" method="post" action="<?=
        Router::url(array(
            'controller' => 'notifications',
            'action' => 'mass_action'
            )
        );
    ?>">
        <div style="margin-bottom:10px;">
            <input type="checkbox" id="notificationsForm_checkall"
                class="checkall_box" title="Check All"
                style="display:inline-block; margin:0;" />
            <label for="notificationsForm_checkall" style="pointer:cursor; display: inline-block;">
                Check all
            </label>

            <span style="margin-left:2em">Action for Selected Notifications:</span>
            <input type="submit" value="Mark Read" name="mass_action" class="btn btn-primary"/>
            <input type="submit" value="Mark all notifications read" name="mark_all" class="btn btn-success" style="float:right" id="mark_all_btn"/>
        </div>
        <table id="notifications_table" class="hover" data-ajax-url="<?=
            Router::url(
                array(
                    'controller' => 'notifications',
                    'action' => 'data_tables'
                )
            );?>"
        >
            <thead>
                <tr>
                        <th>Select</th>
                        <th>ID</th>
                        <th>Exception Name</th>
                        <th>Message</th>
                        <th>PMA Version</th>
                        <th>Exception Type</th>
                        <th>Time</th>
                </tr>
            </thead>
            <tbody>
            <!-- Table is populated using AJAX-jQuery datatable plugin. -->
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
              </tr>
            </tfoot>
        </table>
    </form>
</div>
