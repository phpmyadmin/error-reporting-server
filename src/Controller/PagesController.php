<?php

/**
 * Static content controller.
 *
 * This file will render views from views/pages/
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

namespace App\Controller;

use Cake\Core\Configure;
use Cake\Http\Exception\NotFoundException;
use Cake\Utility\Inflector;
use Cake\View\Exception\MissingViewException;
use function count;
use function func_get_args;
use function implode;

/**
 * Static content controller.
 *
 * Override this controller by placing a copy in controllers directory of an application
 *
 * @see    http://book.cakephp.org/2.0/en/controllers/pages-controller.html
 */
class PagesController extends AppController
{
    /**
     * Controller name.
     *
     * @var string
     */
    public $name = 'Pages';

    /**
     * This controller does not use a model.
     *
     * @var array
     */
    public $uses = [];

    /**
     * Displays a view.
     *
     * @throws NotFoundException when the view file could not be found
     *                           or MissingViewException in debug mode.
     * @return mixed A Response of nothing
     */
    public function display()
    {
        $path = func_get_args();

        $count = count($path);
        if (! $count) {
            return $this->redirect('/');
        }
        $page = $subpage = $title_for_layout = null;
        if (! empty($path[0])) {
            $page = $path[0];
        }
        if (! empty($path[1])) {
            $subpage = $path[1];
        }
        if (! empty($path[$count - 1])) {
            $title_for_layout = Inflector::humanize($path[$count - 1]);
        }
        $this->set([
            'page' => $page,
            'subpage' => $subpage,
            'title_for_layout' => $title_for_layout,
        ]);

        try {
            $this->render(implode('/', $path));
        } catch (MissingViewException $e) {
            if (Configure::read('debug')) {
                throw $e;
            }
            throw new NotFoundException();
        }
    }
}
