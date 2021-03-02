<?php
/**
 * Report Notification Email Template - Layout
 *
 * phpMyAdmin Error reporting server
 * Copyright (c) phpMyAdmin project (https://www.phpmyadmin.net/)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) phpMyAdmin project (https://www.phpmyadmin.net/)
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 *
 * @see          https://www.phpmyadmin.net/
 */
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN">
<html>
<head>
	<title>New Report Notification - phpMyAdmin Error Reporting System</title>
	<style type="text/css">
		th, td {
		    border: 1px solid #ddd;
        }
	</style>
</head>
<body>
	<?php echo $this->fetch('content'); ?>

	<p>
		<i>This email was automatically sent by
			<a href="https://phpmyadmin.net">phpMyAdmin's</a>
			 <a href="https://reports.phpmyadmin.net">Error Reporting System</a>.
		</i>
	</p>
</body>
</html>