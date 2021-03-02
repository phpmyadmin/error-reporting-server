<?php
/**
 * Default Non-logged in page (http://reports.phpmyadmin.net)
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

use Cake\Utility\Inflector;

?>

<!DOCTYPE html>
<html>
    <head>
        <?= $this->Html->charset(); ?>
        <title>
            <?= $this->fetch('title'); ?>
            phpMyAdmin - Error Reporting Server
        </title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <?= $this->Html->meta('icon'); ?>

        <!-- CSS Files -->
        <?= $this->Html->css($css_files); ?>

        <!-- JS Files -->
        <?= $this->Html->script($js_files); ?>

        <!-- set up js global variable for notifications count -->
        <?=
            $this->Html->scriptBlock(
                'var notifications_count = ' . $notif_count . ';',
                array(
                    'inline' => true
                )
            );
        ?>
    </head>

    <body>
        <div class="navbar">
            <div class="navbar-inner">
                <a class="brand" href="<?= $baseURL; ?>">phpMyAdmin</a>
                <?php if ($developer_signed_in): ?>
                    <ul class="nav">
                        <?php
                            $controllers = array('reports');

                            // Show these only if Developer has commit access
                            if (! $read_only) {
                                $controllers[] = 'stats';
                                $controllers[] = 'notifications';
                            }
                            foreach ($controllers as $controller) {
                                $class = '';
                                if ($current_controller === $controller) {
                                    $class = 'active';
                                }
                                echo "<li class='$class' id='nav_"
                                    . $controller . "'><a href='"
                                    . $baseURL . $controller . "'>";
                                echo Inflector::humanize($controller);
                                echo "</a></li>";
                            }
                        ?>
                    </ul>
                <?php endif; ?>
                <ul class="nav pull-right">
                    <?php if ($developer_signed_in): ?>
                        <li>
                            <p class="navbar-text">
                                Hello, <?= $current_developer['full_name']; ?>
                            </p>
                        </li>
                        <li>
                            <a href="<?= $baseURL.'developers/logout'; ?>">
                                Logout
                            </a>
                        </li>
                    <?php else: ?>
                        <li>
                            <a href="<?= $baseURL.'developers/login'; ?>">
                                Login with Github
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        <div id="container">
            <div id="header"></div>
            <div id="content" class="container">
                <?= $this->Flash->render(); ?>
                <?= $this->fetch('content'); ?>
            </div>
            <div id="footer"></div>
        </div>
    </body>
</html>
