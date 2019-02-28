<?php

/* vim: set expandtab sw=4 ts=4 sts=4: */

/**
 * Mailer component handling sending of report notification mails.
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
use Cake\Core\Configure;
use Cake\Mailer\Email;

/**
 * Mailer component handling report notification emails.
 */
class MailerComponent extends Component
{
    /**
     * Send an email about the report to configured Email.
     *
     * @param $viewVars Array of View Variables
     *
     * @return bool if Email was sent
     */
    public function sendReportMail($viewVars)
    {
        $email = new Email('default');
        $emailTo = Configure::read('NotificationEmailsTo');
        $emailFrom = Configure::read('NotificationEmailsFrom');
        $emailTransport = Configure::read('NotificationEmailsTransport');

        if (!$emailTo || '' === $emailTo
            || !$emailFrom || '' === $emailFrom
        ) {
            return false;
        }

        $email->setTransport($emailTransport)
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
