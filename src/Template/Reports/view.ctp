<?php use Cake\Routing\Router; ?>
<div>
	<h1 style="display: inline">Error Report #<?php echo $report[0]['id'] ?>
		<small>[<?php echo $status[$report[0]['status']]; ?>]</small>
	</h1>
	<a href="#" onclick="showStateForm(); return false">Change state</a>
</div>
<br />
<form class="form-inline" id="state-form" style="display: none"
		action="/<?php echo BASE_DIR ?>reports/change_state/<?php echo $report[0]["id"]; ?>"
		method="post">
	<span>Change state to:</span>
	<?php echo $this->Form->select('state', $status, array('value' =>
			$report[0]["status"], 'empty' => false)); ?>
	<input type="submit" value="Change" class="btn btn-primary" />
</form>
<?php if (empty($related_reports)) { ?>
<form class="form-inline" action="/<?php echo BASE_DIR ?>reports/mark_related_to/<?php
      echo $report["Report"]["id"]; ?>">
    <span>Mark the same as:</span>
    <input type="number" min="1" name="related_to" />
    <input type="submit" value="Submit" class="btn btn-primary" />
  </form>
<?php } else { ?>
  <p>
    This report has been marked the same as the following reports:
    (<?php echo $this->Reports->createReportsLinks($related_reports); ?>).
    <a href="/<?php echo BASE_DIR ?>reports/unmark_related_to/<?php echo $report[0]["id"]; ?>">
      Remove from this group
    </a>
  </p>
<?php } ?>
<table cellspacing="0" class="table table-bordered error-report">
  <tr>
    <td>Error Type</td>
    <td><?php echo (($incidents[0]["exception_type"] == 1) ? ('php') : ('js') ); ?></td>
  </tr>
  <tr>
    <td>Error Name</td>
    <td><?php echo $report[0]["error_name"]; ?></td>
  </tr>
  <tr>
    <td>Error Message</td>
    <td><?php echo $report[0]["error_message"]; ?></td>
  </tr>
  <tr>
    <td>Sourceforge Report</td>
    <td>
    <?php
    if($report[0]['sourceforge_bug_id']) {
      echo $this->Html->link('#' . $report[0]['sourceforge_bug_id'],
          "https://sourceforge.net/p/$project_name/bugs/".
          $report[0]['sourceforge_bug_id'] . "/");
      echo '<form action="'
          . Router::url('/source_forge/unlink_ticket/', true)
          . $report[0]['id']
          .'" method="GET" class="form-horizontal" style="margin-bottom:5px;"'
          . ' onclick="return window.confirm(\'Are you sure you want to unlink??\');" >';
      echo $this->Form->input('UnLink with Ticket', array(
        'type' => 'submit',
        'label' => false,
        'class'=>'btn btn-primary'
        )
      );
      echo '</form>';
    } else {
      echo '<table cellspacing="0" class="table table-bordered error-report"'
          . ' style="width:300px; margin-bottom:5px;">'
          . '<tr><td style="min-width:130px;">';
      echo $this->Html->link('Create New Ticket', '/source_forge/create_ticket/'
          . $report[0]['id']);

      echo '</td><td style="min-width:130px;">';

      echo '<form action="'
          . Router::url('/', true)
          .'source_forge/link_ticket/'
          . $report[0]['id']
          .'" method="GET" class="form-horizontal" style="margin-bottom:5px;">';
      echo $this->Form->input('ticket_id', array(
        'placeholder' => 'Ticket Number',
        'type' => 'text',
        'label' => false,
        'div' => true,
        'class' => 'input-large',
        'name' => 'ticket_id'
        )
      );
      echo '<br/>';
      echo $this->Form->input('Link with existing Ticket', array(
        'placeholder' => 'Ticket Number',
        'type' => 'submit',
        'label' => false,
        'div' => '',
        'class'=>'btn btn-primary'
        )
      );
      echo '</form>';
      echo '</td></tr></table>';
    }
    ?>
    </td>
  </tr>
  <tr>
    <td>PMA Versions</td>
    <td>
      <?php echo $this->Reports->entriesFromIncidents(
          $related_entries["pma_version"], $pma_version_distinct_count, "pma_version");
      ?>
    </td>
  </tr>
  <tr>
    <td>PHP Versions</td>
    <td>
      <?php echo $this->Reports->entriesFromIncidents(
          $related_entries["php_version"], $php_version_distinct_count, "php_version");
      ?>
    </td>
  </tr>
  <tr>
    <td>Browsers</td>
    <td>
      <?php echo $this->Reports->entriesFromIncidents(
          $related_entries["browser"], $browser_distinct_count, "browser");
      ?>
    </td>
  </tr>
  <?php if ($incidents[0]["exception_type"] == 1) { // php ?>
    <tr>
      <td>Location</td>
      <td><?php echo $report[0]["location"]; ?></td>
    </tr>
    <tr>
      <td>Line Number</td>
      <td><?php echo $report[0]["linenumber"]; ?></td>
    </tr>
  <?php } else { ?>
    <tr>
      <td>Script Name</td>
      <td>
        <?php echo $this->Reports->entriesFromIncidents(
            $related_entries["script_name"], $script_name_distinct_count, "script_name");
        ?>
      </td>
    </tr>
  <?php } ?>
  <tr>
    <td>Configuration Storage</td>
    <td>
      <?php echo $this->Reports->entriesFromIncidents(
          $related_entries["configuration_storage"], $configuration_storage_distinct_count, "configuration_storage");
      ?>
    </td>
  </tr>
  <tr>
    <td>Server Software</td>
    <td>
      <?php echo $this->Reports->entriesFromIncidents(
          $related_entries["server_software"], $server_software_distinct_count, "server_software");
      ?>
    </td>
  </tr>
  <tr>
    <td>User OS</td>
    <td>
      <?php echo $this->Reports->entriesFromIncidents(
          $related_entries["user_os"], $user_os_distinct_count, "user_os");
      ?>
    </td>
  </tr>
  <tr>
    <td>Incident Count</td>
    <td><?php echo count($incidents) . " incidents of this bug"; ?></td>
  </tr>
  <tr>
    <td>Submission Date</td>
    <td>
      <?php echo $report[0]["created"]; ?>
      and it was last seen on
      <?php echo $incidents[0]["created"]; ?>
    </td>
  </tr>
  <tr>
    <td>Incidents</td>
    <td>
      <?php echo $this->Incidents->createIncidentsLinks($incidents); ?>
    </td>
  </tr>
</table>

<h4>Stacktraces:</h4>
<?php echo $this->Reports->getStacktracesForIncidents($incidents_with_stacktrace); ?>

<h4>Descriptions submitted by users:</h4>
<?php echo $this->Incidents->incidentsDescriptions($incidents_with_description); ?>
<h4>Stats and Graphs</h4>
<span id="graphs"></span>
<script type="text/javascript">
  <?php echo $this->Reports->getChartArray("chartArray", $columns,
      $related_entries); ?>
  window.onload = function () {
    chartArray.forEach(function(chart) {
      var span_id = "graph_" + chart.name;
      var $span = $("<span class='span5'>").attr("id", span_id);
      $("#graphs").append($span);
      piechart(span_id, chart.title, chart.values, chart.labels);
    });
  };
</script>
