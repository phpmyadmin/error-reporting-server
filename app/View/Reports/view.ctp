<h1>Error Report #<?php echo $report["Report"]["id"]; ?>
  <small>[<?php echo $report["Report"]["status"]; ?>]</small>
</h1>
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
      echo $this->Html->link('Submit report', '/source_forge/new_ticket/'
          . $report['Report']['id']);
    } ?>
    </td>
  </tr>
  <tr>
    <td>PMA Versions</td>
    <td>
      <?php echo $this->Reports->entriesFromRelateReports(
          $pma_version_related_entries, $pma_version_distinct_count);
      ?>
    </td>
  </tr>
  <tr>
    <td>PHP Versions</td>
    <td>
      <?php echo $this->Reports->entriesFromRelateReports(
          $php_version_related_entries, $php_version_distinct_count);
      ?>
    </td>
  </tr>
  <tr>
    <td>Browsers</td>
    <td>
      <?php echo $this->Reports->entriesFromRelateReports(
          $browser_related_entries, $browser_distinct_count);
      ?>
    </td>
  </tr>
  <tr>
    <td>Server Software</td>
    <td>
      <?php echo $this->Reports->entriesFromRelateReports(
          $server_software_related_entries, $server_software_distinct_count);
      ?>
    </td>
  </tr>
  <tr>
    <td>Reports Count</td>
    <td><?php echo count($related_reports) ." reports of this bug"; ?></td>
  </tr>
  <tr>
    <td>Submition Date</td>
    <td>
      <?php echo $report["Report"]["created"]; ?>
      and it was last seen on
      <?php echo $related_reports[0]["Report"]["created"]; ?>
    </td>
  </tr>
  <tr>
    <td>Steps leading to the error</td>
    <td>
      <?php if ($report["Report"]["steps"]) { ?>
        <pre><?php echo $report["Report"]["steps"]; ?></pre>
      <?php } ?>
      <p>Related reports with a description:
        <?php echo $this->Reports->
            createReportsLinks($reports_with_description); ?>
      </p>
    </td>
  </tr>
  <tr>
    <td>Stack</td>
    <td>
      <a href="#" id="toggle-stacktrace">Show stacktrace</a>
      <pre id="stacktrace" style="display: none;"><?php echo
        json_encode($report["Report"]["full_report"]['exception']['stack'],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); ?></pre>
    </td>
  </tr>
  <tr>
    <td>Full Report</td>
    <td>
      <a href="/reports/json/<?php echo $report["Report"]["id"]; ?>">click here</a>
    </td>
  </tr>
  <tr>
    <td>Related reports</td>
    <td>
      <?php echo $this->Reports->createReportsLinks($related_reports); ?>
    </td>
  </tr>
</table>
