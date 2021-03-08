<?php
/**
 * Routes configuration
 *
 * In this file, you set up routes to your controllers and their actions.
 * Routes are very important mechanism that allows you to freely connect
 * different URLs to chosen controllers and their actions (functions).
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
use Cake\Routing\Route\Route;
use Cake\Routing\Router;

/**
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
 * inconsistently cased URLs when used with `:plugin`, `:controller` and
 * `:action` markers.
 *
 * Cache: Routes are cached to improve performance, check the RoutingMiddleware
 * constructor in your `src/Application.php` file to change this behavior.
 */
Router::defaultRouteClass(Route::class);


Router::scope('/', static function ($routes): void {
    /**
     * Here, we are connecting '/' (base path) to a controller called 'Pages',
     * its action called 'display', and we pass a param to select the view file
     * to use (in this case, src/Template/Pages/home.php)...
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
    /**
     * Connect catchall routes for all controllers.
     *
     * Using the argument `DashedRoute`, the `fallbacks` method is a shortcut for
     *
     * ```
     * $routes->connect('/:controller', ['action' => 'index'], ['routeClass' => 'DashedRoute']);
     * $routes->connect('/:controller/:action/*', [], ['routeClass' => 'DashedRoute']);
     * ```
     *
     * Any route class can be used with this method, such as:
     * - DashedRoute
     * - InflectedRoute
     * - Route
     * - Or your own route class
     *
     * You can remove these routes once you've connected the
     * routes you want in your application.
     */
    $routes->fallbacks(InflectedRoute::class);
});

/**
 * If you need a different set of middleware or none at all,
 * open new scope and define routes there.
 *
 * ```
 * Router::scope('/api', function (RouteBuilder $routes) {
 *     // No $routes->applyMiddleware() here.
 *     // Connect API actions here.
 * });
 * ```
 */
