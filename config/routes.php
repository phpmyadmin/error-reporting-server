<?php
/**
 * Routes configuration.
 *
 * In this file, you set up routes to your controllers and their actions.
 * Routes are very important mechanism that allows you to freely connect
 * different URLs to chosen controllers and their actions (functions).
 *
 * It's loaded within the context of `Application::routes()` method which
 * receives a `RouteBuilder` instance `$routes` as method argument.
 *
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */

use Cake\Routing\Route\InflectedRoute;
use Cake\Routing\RouteBuilder;

/*
 * This file is loaded in the context of the `Application` class.
 * So you can use `$this` to reference the application class instance
 * if required.
 */
return function (RouteBuilder $routes): void {
    /*
     * The default class to use for all routes
     *
     * The following route classes are supplied with CakePHP and are appropriate
     * to set as the default:
     *
     * - Route
     * - InflectedRoute
     * - DashedRoute
     *
     * If no call is made to `Router::defaultRouteClass()`, the class used is
     * `Route` (`Cake\Routing\Route\Route`)
     *
     * Note that `Route` does not do any inflections on URLs which will result in
     * inconsistently cased URLs when used with `{plugin}`, `{controller}` and
     * `{action}` markers.
     */
    $routes->setRouteClass(InflectedRoute::class);
    /*
     * Here, we are connecting '/' (base path) to a controller called 'Pages',
     * its action called 'display', and we pass a param to select the view file
     * to use (in this case, templates/Pages/home.php)...
     */
    $routes->connect('/', ['controller' => 'Pages', 'action' => 'display', 'home']);

    /**
     * ...and connect the rest of 'Pages' controller's URLs.
     */
    $routes->connect('/pages/*', ['controller' => 'Pages', 'action' => 'display']);

    $routes->connect('/stats', ['controller' => 'Stats', 'action' => 'stats']);

    $routes->connect(
        '/reports/view/{id}',
        ['controller' => 'Reports', 'action' => 'view'],
        ['_name' => 'reports:view']
    )
            ->setPass(['id', 'reportId'])
            ->setPatterns([
                'id' => '[0-9]+',
            ]);

    $routes->connect(
        '/github/sync_issue_status',
        ['controller' => 'Github', 'action' => 'sync_issue_status']
    );

    $routes->connect(
        '/github/create_issue/{id}',
        ['controller' => 'Github', 'action' => 'create_issue'],
        ['_name' => 'github:create_issue']
    )
            ->setPass(['id', 'reportId'])
            ->setPatterns([
                'id' => '[0-9]+',
            ]);

    $routes->connect(
        '/github/unlink_issue/{id}',
        ['controller' => 'Github', 'action' => 'unlink_issue'],
        ['_name' => 'github:unlink_issue']
    )
            ->setPass(['id', 'reportId'])
            ->setPatterns([
                'id' => '[0-9]+',
            ]);

    $routes->connect(
        '/github/link_issue/{id}',
        ['controller' => 'Github', 'action' => 'link_issue'],
        ['_name' => 'github:link_issue']
    )
            ->setPass(['id', 'reportId'])
            ->setPatterns([
                'id' => '[0-9]+',
            ]);

    /*
     * Connect catchall routes for all controllers.
     *
     * The `fallbacks` method is a shortcut for
     *
     * ```
     * $builder->connect('/{controller}', ['action' => 'index']);
     * $builder->connect('/{controller}/{action}/*', []);
     * ```
     *
     * It is NOT recommended to use fallback routes after your initial prototyping phase!
     * See https://book.cakephp.org/5/en/development/routing.html#fallbacks-method for more information
     */
    $routes->fallbacks();

/*
 * If you need a different set of middleware or none at all,
 * open new scope and define routes there.
 *
 * ```
 * $routes->scope('/api', function (RouteBuilder $builder): void {
 *     // No $builder->applyMiddleware() here.
 *
 *     // Parse specified extensions from URLs
 *     // $builder->setExtensions(['json', 'xml']);
 *
 *     // Connect API actions here.
 * });
 * ```
 */
 };
