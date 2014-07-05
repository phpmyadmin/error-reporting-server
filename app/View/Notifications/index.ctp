<div class="notifications index">
	<h2><?php echo __('Notifications'); ?></h2>
	<table id="notifications_table" class="hover" data-ajax-url="<?php 
	    echo Router::url(array(
	      'controller' => 'notifications',
	      'action' => 'data_tables')
	    );
	  ?>">
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
	</table>
</div>

