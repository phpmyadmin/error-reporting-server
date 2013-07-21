<h1>Reports</h1>
<table id="reports_table" data-ajax-url="<?php 
    echo Router::url(array(
      'controller' => 'reports',
      'action' => 'ajax')
    );
  ?>">
  <thead>
    <tr>
      <th><input id="id_filter" style="width: 100%;margin-left:-10px" type="number"/></th>
      <th>
        <select id="error_name_filter" style="width: 100%">
          <option></option>
          <?php
            foreach ($distinct_error_names as $id => $name) { 
              echo "<option value='$name'>$name</option>";
            }
          ?>
        </select>
      </th>
      <th></th>
      <th>
        <select id="pam_version_filter" style="width: 100%">
          <option></option>
          <?php
            foreach ($distinct_versions as $id => $version) { 
              echo "<option value='$version'>$version</option>";
            }
          ?>
        </select>
      </th>
      <th>
        <select id="status_filter" style="width: 100%">
          <option></option>
          <?php
            foreach ($distinct_statuses as $id => $status) { 
              echo "<option value='$status'>$status</option>";
            }
          ?>
        </select>
      </th>
    </tr>
    <tr>
      <th>ID</th>
      <th>Exception Name</th>
      <th>Message</th>
      <th>PMA Version</th>
      <th>Status</th>
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
    </tr>
  </tfoot>
</table>
