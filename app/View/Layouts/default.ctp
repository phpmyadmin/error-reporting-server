<?php
/**
 *
 * PHP 5
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @package       app.View.Layouts
 * @since         CakePHP(tm) v 0.10.0.1076
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

?>
<!DOCTYPE html>
<html>
<head>
	<?php echo $this->Html->charset(); ?>
	<title>
		<?php echo $title_for_layout; ?>
    PHP MY ADMIN - Error Reporting Server
	</title>
	<?php
		echo $this->Html->meta('icon');

		//echo $this->Html->css('cake.generic');
		echo $this->Html->css('jquery.dataTables');
		echo $this->Html->css('jquery.dataTables_themeroller');
		echo $this->Html->css('bootstrap.min');
		echo $this->Html->css('bootstrap-responsive.min');
		echo $this->Html->css('custom');

		echo $this->Html->script('jquery');
		echo $this->Html->script('jquery.dataTables.min');
		echo $this->Html->script('bootstrap');
		echo $this->Html->script('custom');

		echo $this->fetch('meta');
		echo $this->fetch('css');
		echo $this->fetch('script');
	?>
</head>
<body>
  <div class="navbar">
    <div class="navbar-inner">
      <a class="brand" href="/">phpMyAdmin</a>
      <ul class="nav">
        <li class="<?php echo $navigation_class; ?>"><a href="/reports">Reports</a></li>
      </ul>
      <ul class="nav pull-right">
        <?php if ($developer_signed_in) { ?>
          <li>
            <p class="navbar-text">Hello, <?php echo $current_developer["full_name"]; ?></p>
          </li>
          <li><a href="/developers/logout">Logout</a></li>
        <?php } else { ?>
          <li><a href="/developers/login">Login with Github</a></li>
        <?php } ?>
      </ul>
    </div>
  </div>
	<div id="container">
		<div id="header">
		</div>
		<div id="content" class="container">
			<?php echo $this->Session->flash(); ?>

			<?php echo $this->fetch('content'); ?>
		</div>
		<div id="footer">
		</div>
	</div>
</body>
</html>
