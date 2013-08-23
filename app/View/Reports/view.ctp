<h1>Error Report #<?php echo $report["Report"]["id"]; ?>
  <small>[<?php echo $report["Report"]["status"]; ?>]</small>
</h1>
<?php if (empty($related_reports)) { ?>
  <form class="form-inline" action="/reports/mark_related_to/<?php
      echo $report["Report"]["id"]; ?>">
    <span>Mark the same as:</span>
    <input type="number" name="related_to" />
    <input type="submit" value="submit" class="btn btn-primary" />
  </form>
<?php } else { ?>
  <p>
    This report has been marked the same as the following reports:
    (<?php echo $this->Reports->createReportsLinks($related_reports); ?>).
    <a href="/reports/unmark_related_to/<?php echo $report["Report"]["id"]; ?>">
      Remove from this group
    </a>
  </p>
<?php } ?>
<table cellspacing="0" class="table table-bordered error-report">
  <tr>
    <td>Error Name</td>
    <td><?php echo $report["Report"]["error_name"]; ?></td>
  </tr>
  <tr>
    <td>Error Message</td>
    <td><?php echo $report["Report"]["error_message"]; ?></td>
  </tr>
  <tr>
    <td>Sourceforge Report</td>
    <td><?php if($report['Report']['sourceforge_bug_id']) {
      echo $this->Html->link('#' . $report['Report']['sourceforge_bug_id'],
          "https://sourceforge.net/p/$project_name/bugs/".
          $report['Report']['sourceforge_bug_id'] . "/");
    } else {
      echo $this->Html->link('Submit report', '/source_forge/create_ticket/'
          . $report['Report']['id']);
    } ?>
    </td>
  </tr>
  <tr>
    <td>PMA Versions</td>
    <td>
      <?php echo $this->Reports->entriesFromIncidents(
          $related_entries["pma_version"], $pma_version_distinct_count);
      ?>
    </td>
  </tr>
  <tr>
    <td>PHP Versions</td>
    <td>
      <?php echo $this->Reports->entriesFromIncidents(
          $related_entries["php_version"], $php_version_distinct_count);
      ?>
    </td>
  </tr>
  <tr>
    <td>Browsers</td>
    <td>
      <?php echo $this->Reports->entriesFromIncidents(
          $related_entries["browser"], $browser_distinct_count);
      ?>
    </td>
  </tr>
  <tr>
    <td>Script Name</td>
    <td>
      <?php echo $this->Reports->entriesFromIncidents(
          $related_entries["script_name"], $script_name_distinct_count);
      ?>
    </td>
  </tr>
  <tr>
    <td>Configuration Storage</td>
    <td>
      <?php echo $this->Reports->entriesFromIncidents(
          $related_entries["configuration_storage"], $configuration_storage_distinct_count);
      ?>
    </td>
  </tr>
  <tr>
    <td>Server Software</td>
    <td>
      <?php echo $this->Reports->entriesFromIncidents(
          $related_entries["server_software"], $server_software_distinct_count);
      ?>
    </td>
  </tr>
  <tr>
    <td>User OS</td>
    <td>
      <?php echo $this->Reports->entriesFromIncidents(
          $related_entries["user_os"], $user_os_distinct_count);
      ?>
    </td>
  </tr>
  <tr>
    <td>Incident Count</td>
    <td><?php echo count($incidents) . " incidents of this bug"; ?></td>
  </tr>
  <tr>
    <td>Submition Date</td>
    <td>
      <?php echo $report["Report"]["created"]; ?>
      and it was last seen on
      <?php echo $incidents[0]["Incident"]["created"]; ?>
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

<h4>Descriptions submited by users:</h4>
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
