<?php
/* vim: set expandtab sw=4 ts=4 sts=4: */

/**
 * Mailer component handling sending of report notification mails
 *
 * phpMyAdmin Error reporting server
 * Copyright (c) phpMyAdmin project (https://www.phpmyadmin.net/)
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

namespace App\Controller\Component;

use Cake\Controller\Component;
use Cake\ORM\TableRegistry;
use Cake\Mailer\Email;
use Cake\Core\Configure;

/**
 * Mailer component handling report notification emails.
 */
class MailerComponent extends Component
{
    /**
     * Send an email about the report to configured Email
     *
     * @param $viewVars Array of View Variables
     *
     * @return boolean if Email was sent
     */
    public function sendReportMail($viewVars)
    {
        $email = new Email();
        $emailTo = Configure::read('NotificationEmailsTo');
        $emailFrom = Configure::read('NotificationEmailsFrom');

        if (! $emailTo || $emailTo === ''
            || ! $emailFrom || $emailFrom === ''
        ) {
            return false;
        }

        $email->transport('default')
            ->viewVars($viewVars)
            ->subject(
                sprintf(
                    'A new report has been submitted on the Error Reporting Server: %s',
                    $viewVars['report']['id']
                )
            )
            ->template('report', 'default')
            ->emailFormat('html')
            ->to($emailTo)
            ->from($emailFrom)
            ->send();

        return true;
    }
}
