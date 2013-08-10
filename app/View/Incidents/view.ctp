<h1>Incident #<?php echo $incident["Incident"]["id"]; ?>
  <small>[Report <?php echo $this->Reports->linkToReport($incident); ?>]</small>
</h1>
<table cellspacing="0" class="table table-bordered error-report">
  <tr>
    <td>Error Name</td>
    <td>
      <?php echo $incident["Incident"]["error_name"]; ?>
    </td>
  </tr>
  <tr>
    <td>Error Message</td>
    <td>
      <?php echo $incident["Incident"]["error_message"]; ?>
    </td>
  </tr>
  <tr>
    <td>PMA Version</td>
    <td>
      <?php echo $incident["Incident"]["full_report"]["pma_version"]; ?>
    </td>
  </tr>
  <tr>
    <td>PHP Version</td>
    <td>
      <?php echo $incident["Incident"]["full_report"]["php_version"]; ?>
    </td>
  </tr>
  <tr>
    <td>Useragent</td>
    <td>
      <?php echo $incident["Incident"]["full_report"]["user_agent_string"]; ?>
    </td>
  </tr>
  <tr>
    <td>Server Software</td>
    <td>
      <?php echo $incident["Incident"]["full_report"]["server_software"]; ?>
    </td>
  </tr>
  <tr>
    <td>User OS</td>
    <td>
      <?php echo $incident["Incident"]["user_os"]; ?>
    </td>
  </tr>
  <tr>
    <td>Submition Date</td>
    <td>
      <?php echo $incident["Incident"]["created"]; ?>
    </td>
  </tr>
</table>

<h4>Stacktrace:</h4>
<?php echo $this->Reports->getStacktrace($incident, "well"); ?>

<h4>Description submited by user:</h4>
<?php echo $this->Reports->incidentsDescriptions(array($incident)); ?>
