<?php

namespace App\Mailer\Transport;

use Cake\Core\Configure;
use Cake\Mailer\AbstractTransport;
use Cake\Mailer\Email;
use function implode;
use function trim;

/**
 * Test environment Email Transport
 */
class TestTransport extends AbstractTransport
{
    /**
     * Send mail.
     *
     * @param Email $email Cake Email
     * @return string[]
     */
    public function send(Email $email): array
    {
        $headers = $email->getHeaders(
            [
                'from',
                'sender',
                'replyTo',
                'readReceipt',
                'returnPath',
                'to',
                'cc',
                'subject',
            ]
        );

        trim($this->_headersToString($headers));
        $message = trim(implode("\r\n", (array) $email->message()));
        $result = [
            'headers' => $headers,
            'message' => $message,
        ];

        Configure::write('test_transport_email', $result);

        return $result;
    }
}
