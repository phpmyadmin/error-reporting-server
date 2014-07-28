<?php
/**
 *
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

/* Define baseURL */
$baseURL = Router::url('/',true);
?>
<!DOCTYPE html>
<html>
<head>
	<?php echo $this->Html->charset(); ?>
	<title>
		<?php echo $title_for_layout; ?>
		phpMyAdmin - Error Reporting Server
	</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<?php
		echo $this->Html->meta('icon');
		//echo $this->Html->css('cake.generic');
		echo $this->Html->css('jquery.dataTables');
		echo $this->Html->css('jquery.dataTables_themeroller');
		echo $this->Html->css('bootstrap.min');
		echo $this->Html->css('bootstrap-responsive.min');
		echo $this->Html->css('shCore.css');
		echo $this->Html->css('shThemeDefault.css');
		echo $this->Html->css('custom');

		echo $this->Html->script('jquery');
		echo $this->Html->script('jquery.dataTables.min');
		echo $this->Html->script('bootstrap');
		echo $this->Html->script('shCore');
		echo $this->Html->script('shBrushXml');
		echo $this->Html->script('shBrushJScript');
		echo $this->Html->script('raphael-min');
		echo $this->Html->script('g.raphael-min');
		echo $this->Html->script('g.pie-min');
		echo $this->Html->script('g.line-min');
		echo $this->Html->script('g.bar-min');
		echo $this->Html->script('g.dot-min');
		echo $this->Html->script('jquery.jqplot.min.js');
		echo $this->Html->script('jqplot.barRenderer.min.js');
		echo $this->Html->script('jqplot.highlighter.min.js');
		echo $this->Html->script('jqplot.dateAxisRenderer.min.js');
		echo $this->Html->script('jqplot.categoryAxisRenderer.min.js');
		echo $this->Html->script('jqplot.pointLabels.min.js');
		echo $this->Html->script('jqplot.canvasTextRenderer.min.js');
		echo $this->Html->script('jqplot.canvasAxisTickRenderer.min.js');
		echo $this->Html->script('jqplot.cursor.min.js');
		echo $this->Html->script('pie');
		echo $this->Html->script('custom');

		echo $this->fetch('meta');
		echo $this->fetch('css');
		echo $this->fetch('script');
		// set up js global variable for notifications count
		echo $this->Html->scriptBlock('var notifications_count = ' . $notif_count . ';',array('inline'=>true));
	?>
</head>
<body>
  <div class="navbar">
    <div class="navbar-inner">
      <a class="brand" href="<?php echo $baseURL; ?>">phpMyAdmin</a>
      <ul class="nav">
				<?php
					$controllers = array('reports', 'stats', 'notifications');
					foreach ($controllers as $controller) {
						$class = '';
						if ($current_controller === $controller) {
							$class = 'active';
						}
						echo "<li class='$class' id='nav_".$controller."'><a href='".$baseURL.$controller."'>";
						echo Inflector::humanize($controller);
						echo "</a></li>";
					}
				?>
      </ul>
      <ul class="nav pull-right">
        <?php if ($developer_signed_in) { ?>
          <li>
            <p class="navbar-text">Hello, <?php echo $current_developer["full_name"]; ?></p>
          </li>
          <li><a href="<?php echo $baseURL.'developers/logout'; ?>" >Logout</a></li>
        <?php } else { ?>
          <li><a href="<?php echo $baseURL.'developers/login'; ?>">Login with Github</a></li>
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
