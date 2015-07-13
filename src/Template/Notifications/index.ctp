<?php use Cake\Routing\Router; ?>
<div class="notifications index">
	<h2><?php echo __('Notifications'); ?></h2>
	<form name="notif_mass_action" method="post" action="<?php 
	    echo Router::url(array(
	      'controller' => 'notifications',
	      'action' => 'mass_action')
	    );
	?>">
	<div style="margin-bottom:10px;">
	<span>Action for Selected Notifications:</span>
	<input type="submit" value="Mark Read" class="btn btn-primary"/>
	</div>
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

