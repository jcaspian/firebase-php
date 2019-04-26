<?php

declare(strict_types=1);

namespace Kreait\Firebase;

use Kreait\Firebase\Exception\InvalidArgumentException;
use Kreait\Firebase\Exception\Messaging\InvalidArgument;
use Kreait\Firebase\Exception\Messaging\InvalidMessage;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Messaging\ApiClient;
use Kreait\Firebase\Messaging\BatchApiClient;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Message;
use Kreait\Firebase\Messaging\RegistrationToken;
use Kreait\Firebase\Messaging\Topic;
use Kreait\Firebase\Messaging\TopicManagementApiClient;
use Kreait\Firebase\Util\JSON;

class Messaging
{
    const FCM_MAX_BATCH_SIZE = 100;
    /**
     * @var ApiClient
     */
    private $messagingApi;

    /**
     * @var TopicManagementApiClient
     */
    private $topicManagementApi;

    /**
     * @var BatchApiClient
     */
    private $batchApi;

    public function __construct(ApiClient $messagingApiClient, TopicManagementApiClient $topicManagementApiClient, BatchApiClient $batchApiClient)
    {
        $this->messagingApi = $messagingApiClient;
        $this->topicManagementApi = $topicManagementApiClient;
        $this->batchApi = $batchApiClient;
    }

    /**
     * @param array|CloudMessage|Message $message
     *
     * @return array
     */
    public function send($message): array
    {
        if (\is_array($message)) {
            $message = CloudMessage::fromArray($message);
        }

        if (!($message instanceof Message)) {
            throw new InvalidArgumentException(
                'Unsupported message type. Use an array or a class implementing %s'.Message::class
            );
        }
        $response = $this->messagingApi->sendMessage($message);

        return JSON::decode((string) $response->getBody(), true);
    }

    /**
     * @param array|CloudMessage|Message $message
     *
     * @return array
     */
    public function sendMulticast($message): array
    {
        if (\is_array($message)) {
            $message = CloudMessage::fromArray($message);
        }

        if (!($message instanceof Message)) {
            throw new InvalidArgumentException(
                'Unsupported message type. Use an array or a class implementing %s'.Message::class
            );
        }

        if (empty($message->tokens) || !\is_array($message->tokens)) {
            throw new InvalidArgumentException('Tokens must be a non-empty array');
        }

        if (count($message->tokens) > self::FCM_MAX_BATCH_SIZE) {
            throw new InvalidArgumentException(
                'Tokens list must not contain more than ' . self::FCM_MAX_BATCH_SIZE . ' items'
            );
        }
        
        $tokens = $this->ensureArrayOfRegistrationTokens($message->tokens);
        
        $messages = array_map(function ($token) {
            return $message->withChangedTarget('token', $token);
        }, $tokens);

        return $this->sendAll($messages);
    }

    /**
     * @param CloudMessage[] $messages
     *
     * @return array
     */
    public function sendAll($messages): array
    {
        if (empty($messages) || !\is_array($messages)) {
            throw new InvalidArgumentException('Messages must be a non-empty array');
        }

        if (count($messages) > self::FCM_MAX_BATCH_SIZE) {
            throw new InvalidArgumentException(
                'Messages list must not contain more than ' . self::FCM_MAX_BATCH_SIZE . ' items'
            );
        }

        $response = $this->batchApi->sendMessages($messages);

        return JSON::decode((string) $response->getBody(), true);
    }

    /**
     * @param array|CloudMessage|Message $message
     *
     * @throws InvalidArgumentException
     * @throws InvalidMessage
     *
     * @return array
     */
    public function validate($message): array
    {
        if (\is_array($message)) {
            $message = CloudMessage::fromArray($message);
        }

        if (!($message instanceof Message)) {
            throw new InvalidArgumentException(
                'Unsupported message type. Use an array or a class implementing %s'.Message::class
            );
        }

        try {
            $response = $this->messagingApi->validateMessage($message);
        } catch (NotFound $e) {
            throw (new InvalidMessage($e->getMessage(), $e->getCode()))
                ->withResponse($e->response());
        }

        return JSON::decode((string) $response->getBody(), true);
    }

    /**
     * @param string|Topic $topic
     * @param RegistrationToken|RegistrationToken[]|string|string[] $registrationTokenOrTokens
     *
     * @return array
     */
    public function subscribeToTopic($topic, $registrationTokenOrTokens): array
    {
        $topic = $topic instanceof Topic ? $topic : Topic::fromValue($topic);
        $tokens = $this->ensureArrayOfRegistrationTokens($registrationTokenOrTokens);

        $response = $this->topicManagementApi->subscribeToTopic($topic, $tokens);

        return JSON::decode((string) $response->getBody(), true);
    }

    /**
     * @param string|Topic $topic
     * @param RegistrationToken|RegistrationToken[]|string|string[] $registrationTokenOrTokens
     *
     * @return array
     */
    public function unsubscribeFromTopic($topic, $registrationTokenOrTokens): array
    {
        $topic = $topic instanceof Topic ? $topic : Topic::fromValue($topic);
        $tokens = $this->ensureArrayOfRegistrationTokens($registrationTokenOrTokens);

        $response = $this->topicManagementApi->unsubscribeFromTopic($topic, $tokens);

        return JSON::decode((string) $response->getBody(), true);
    }

    private function ensureArrayOfRegistrationTokens($tokenOrTokens): array
    {
        if ($tokenOrTokens instanceof RegistrationToken) {
            return [$tokenOrTokens];
        }

        if (\is_string($tokenOrTokens)) {
            return [RegistrationToken::fromValue($tokenOrTokens)];
        }

        if (\is_array($tokenOrTokens)) {
            if (empty($tokenOrTokens)) {
                throw new InvalidArgument('Empty array of registration tokens.');
            }

            return array_map(function ($token) {
                return $token instanceof RegistrationToken ? $token : RegistrationToken::fromValue($token);
            }, $tokenOrTokens);
        }

        throw new InvalidArgument('Invalid registration tokens.');
    }
}
