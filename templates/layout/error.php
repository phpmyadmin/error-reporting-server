<?php
/**
 * Default Error page (http://reports.phpmyadmin.net)
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) phpMyAdmin project (https://www.phpmyadmin.net/)
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 *
 * @see      https://www.phpmyadmin.net/
 */

?>

<!DOCTYPE html>
<html>
    <head>
        <?php echo $this->Html->charset(); ?>
        <title>
            <?php echo $this->fetch('title'); ?>
            phpMyAdmin - Error Reporting Server - Error
        </title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <?php echo $this->Html->meta('icon'); ?>

        <!-- CSS Files -->
        <?php if (isset($css_files)) {
            echo $this->Html->css($css_files);
        }

        ?>
    </head>

    <body>
        <div class="navbar">
            <div class="navbar-inner">
                <a class="brand" href="<?php echo $baseURL; ?>">phpMyAdmin</a>
            </div>
        </div>
        <div id="container">
            <div id="header"></div>
            <div id="content" class="container">
                <?php echo $this->fetch('content'); ?>
                <strong>Code:</strong> <?php echo $code; ?><br>
                <strong>Url:</strong> <?php echo $url; ?><br>
                <?php if (isset($current_developer) && $current_developer !== null) { ?>
                    <br><pre><?php echo $error; ?></pre>
                <?php }

                ?>
            </div>
            <div id="footer"></div>
        </div>
    </body>
</html>
