<?php

/*
 * (c) Jeroen van den Enden <info@endroid.nl>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Endroid\CmSms;

use Endroid\Bundle\CmSmsBundle\Exception\InvalidRecipientException;
use Endroid\Bundle\CmSmsBundle\Exception\InvalidSenderException;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Client
{
    const UNICODE_AUTO = 'auto';
    const UNICODE_FORCE = 'force';
    const UNICODE_NEVER = 'never';

    /**
     * @var string
     */
    protected $baseUrl = 'https://gw.cmtelecom.com/v1.0/message';

    /**
     * @var string
     */
    protected $productToken;

    /**
     * @var array
     */
    protected $options;

    /**
     * @var array
     */
    protected $defaultOptions = [
        'sender' => null,
        'unicode' => self::UNICODE_AUTO,
        'minimumNumberOfMessageParts' => 1,
        'maximumNumberOfMessageParts' => 1,
    ];

    /**
     * @var OptionsResolver
     */
    protected $optionsResolver;

    /**
     * @param string $productToken
     * @param array|null $options
     */
    public function __construct($productToken, array $options = [])
    {
        $this->productToken = $productToken;

        $resolver = new OptionsResolver();
        $resolver->setDefaults($this->defaultOptions);
        $this->options = $resolver->resolve($options + $this->defaultOptions);
    }

    /**
     * @return array
     */
    public static function getUnicodeOptions()
    {
        return [
            self::UNICODE_AUTO,
            self::UNICODE_FORCE,
            self::UNICODE_NEVER,
        ];
    }

    /**
     * @param Message $message
     * @param array|null $options
     */
    public function sendMessage(Message $message, array $options = [])
    {
        $this->sendMessages([$message], $options);
    }

    /**
     * @param Message[] $messages
     * @param array|null $options
     * @throws InvalidSenderException
     */
    public function sendMessages(array $messages, array $options = [])
    {
        $optionsResolver = new OptionsResolver();
        $optionsResolver->setDefaults($this->options);
        $options = $optionsResolver->resolve($options + $this->options);

        $request = (object) [
            'messages' => (object) [
                'authentication' => (object) [
                    'producttoken' => $this->productToken
                ],
                'msg' => $this->createMessagesJson($messages, $options)
            ]
        ];

        header('Content-Type: application/json');
        echo json_encode($request);
        die;
    }

    /**
     * @param array $messages
     * @param array $options
     * @return array
     * @throws InvalidSenderException
     * @throws InvalidRecipientException
     */
    protected function createMessagesJson(array $messages, array $options)
    {
        $messagesJson = [];

        foreach ($messages as $message) {
            if (is_null($message->getFrom()) && is_null($options['sender'])) {
                throw new InvalidSenderException('Please provide an SMS sender for your message');
            }

            if (count($message->getTo()) == 0) {
                throw new InvalidRecipientException('Please provide SMS recipients for your message');
            }

            if (is_null($message->getFrom())) {
                $message->setFrom($options['sender']);
            }

            $messageJson = (object)[
                'from' => $message->getFrom(),
                'to' => $this->createRecipientsJson($message->getTo()),
                'body' => (object)[
                    'content' => $message->getBody()
                ],
                'minimumNumberOfMessageParts' => $options['minimumNumberOfMessageParts'],
                'maximumNumberOfMessageParts' => $options['maximumNumberOfMessageParts'],
            ];

            $messageJson = $this->incorporateUnicodeOption($messageJson, $options['unicode']);

            $messagesJson[] = $messageJson;
        }

        return $messagesJson;
    }

    /**
     * @param array $recipients
     * @return array
     */
    protected function createRecipientsJson(array $recipients)
    {
        $recipientsJson = [];

        foreach ($recipients as $recipient) {
            $recipientsJson[] = (object) [
                'number' => $recipient
            ];
        }

        return $recipientsJson;
    }

    /**
     * @param object $messageJson
     * @param string $unicode
     * @return object
     */
    protected function incorporateUnicodeOption($messageJson, $unicode)
    {
        switch ($unicode) {
            case self::UNICODE_AUTO:
                $messageJson->body->type = 'AUTO';
                break;
            case self::UNICODE_FORCE:
                $messageJson->dcs = 8;
                break;
            default:
                break;
        }

        return $messageJson;
    }
}
