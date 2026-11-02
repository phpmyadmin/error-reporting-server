<?php

namespace App\Test\TestCase\Mailer\Transport;

use App\Mailer\Transport\TestTransport;
use Cake\Core\Configure;
use Cake\Mailer\Message;
use Cake\TestSuite\TestCase;

use function array_flip;
use function array_intersect_key;

class TestTransportTest extends TestCase
{
    protected function buildMessage(): Message
    {
        return (new Message())
            ->setFrom('from@example.com', 'From Name')
            ->setSender('sender@example.com')
            ->setReplyTo('reply@example.com')
            ->setReadReceipt('receipt@example.com')
            ->setReturnPath('return@example.com')
            ->setTo('to@example.com', 'To Name')
            ->setCc('cc@example.com')
            ->setBcc('bcc@example.com')
            ->setSubject('A subject')
            ->setBodyText("  Hello world  \n");
    }

    public function testSendReturnsTheCapturedHeadersAndBody(): void
    {
        $transport = new TestTransport();

        $result = $transport->send($this->buildMessage());

        $this->assertSame(
            [
                'From' => 'From Name <from@example.com>',
                'Reply-To' => 'reply@example.com',
                'Disposition-Notification-To' => 'receipt@example.com',
                'Return-Path' => 'return@example.com',
                'To' => 'To Name <to@example.com>',
                'Cc' => 'cc@example.com',
                'Sender' => 'sender@example.com',
                'Subject' => 'A subject',
            ],
            array_intersect_key(
                $result['headers'],
                array_flip([
                    'From',
                    'Reply-To',
                    'Disposition-Notification-To',
                    'Return-Path',
                    'To',
                    'Cc',
                    'Sender',
                    'Subject',
                ]),
            ),
        );

        // The transport only captures the headers it is interested in.
        $this->assertArrayNotHasKey('Bcc', $result['headers']);

        // The body is trimmed before being captured.
        $this->assertSame('Hello world', $result['message']);
    }

    public function testSendStoresTheResultInTheConfiguration(): void
    {
        $transport = new TestTransport();

        $result = $transport->send($this->buildMessage());

        $this->assertSame($result, Configure::read('test_transport_email'));
        $this->assertSame($result['headers'], Configure::read('test_transport_email')['headers']);
        $this->assertSame('Hello world', Configure::read('test_transport_email')['message']);
    }
}
