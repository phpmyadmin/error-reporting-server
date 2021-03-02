<?php

namespace App\Mailer\Transport;

use Cake\Core\Configure;
use Cake\Mailer\AbstractTransport;
use Cake\Mailer\Message;
use function trim;

/**
 * Test environment Email Transport
 */
class TestTransport extends AbstractTransport
{
    /**
     * Send mail
     *
     * @param Message $message Email mesage.
     * @return array
     * @psalm-return array{headers: string, message: string}
     */
    public function send(Message $message): array
    {
        $headers = $message->getHeaders(
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

        $message = trim($message->getBodyString());
        $result = [
            'headers' => $headers,
            'message' => $message,
        ];

        Configure::write('test_transport_email', $result);

        return $result;
    }
}
