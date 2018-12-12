<?php

namespace BotMan\Drivers\Hangouts;

use BotMan\BotMan\Drivers\Events\GenericEvent;
use BotMan\BotMan\Drivers\HttpDriver;
use BotMan\BotMan\Messages\Attachments\Image;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Incoming\IncomingMessage;
use BotMan\BotMan\Messages\Outgoing\OutgoingMessage;
use BotMan\BotMan\Messages\Outgoing\Question;
use BotMan\BotMan\Users\User;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class HangoutsDriver extends HttpDriver
{
    protected $messages = [];

    const DRIVER_NAME = 'Hangouts';

    /**
     * @param Request $request
     *
     * @return void
     */
    public function buildPayload(Request $request)
    {
        $this->payload = new ParameterBag(json_decode($request->getContent(), true) ?? []);
        $this->event = Collection::make($this->payload->all());
        $this->config = Collection::make($this->config->get('hangouts', []));
    }

    /**
     * Determine if the request is for this driver.
     *
     * @return bool
     */
    public function matchesRequest()
    {
        return $this->event->get('token') === $this->config->get('token') && $this->event->get('type') === 'MESSAGE';
    }

    /**
     * @return bool|GenericEvent
     */
    public function hasMatchingEvent()
    {
        $event = false;
        $type = $this->event->get('type');

        if ($type === 'ADDED_TO_SPACE' || $type === 'REMOVED_FROM_SPACE') {
            $event = new GenericEvent($this->event->toArray());
            $event->setName($this->event->get('type'));
        }

        return $event;
    }

    /**
     * Retrieve the chat message(s).
     *
     * @return array
     */
    public function getMessages()
    {
        if (empty($this->messages)) {
            $this->loadMessages();
        }

        return $this->messages;
    }

    /**
     * Load the messages from the incoming payload.
     */
    protected function loadMessages()
    {
        $message = $this->event->get('message');

        $text = $message['text'];

        if ($this->config->get('strip_annotations') === true && isset($message['annotations'])) {
            $start = $message['annotations'][0]['startIndex'];
            $length = $message['annotations'][0]['length'];
            $text = substr($text, $start + $length + 1);
        }

        $this->messages = [new IncomingMessage($text, $message['sender']['name'], $message['name'], $this->event->toArray())];
    }

    /**
     * @return bool
     */
    public function isConfigured()
    {
        return !empty($this->config->get('token'));
    }

    /**
     * Retrieve User information.
     *
     * @param \BotMan\BotMan\Messages\Incoming\IncomingMessage $matchingMessage
     *
     * @return User
     */
    public function getUser(IncomingMessage $matchingMessage)
    {
        $payload = $matchingMessage->getPayload();

        $user = $payload['message']['sender'];

        return new User($user['name'], null, null, $user['displayName'], $user);
    }

    /**
     * @param \BotMan\BotMan\Messages\Incoming\IncomingMessage $message
     *
     * @return Answer
     */
    public function getConversationAnswer(IncomingMessage $message)
    {
        return Answer::create($message->getText())->setMessage($message);
    }

    /**
     * @param OutgoingMessage|\BotMan\BotMan\Messages\Outgoing\Question $message
     * @param \BotMan\BotMan\Messages\Incoming\IncomingMessage          $matchingMessage
     * @param array                                                     $additionalParameters
     *
     * @return array
     */
    public function buildServicePayload($message, $matchingMessage, $additionalParameters = [])
    {
        $payload = [
            'text'  => '',
            'cards' => [
                [
                    'sections' => [
                        [
                            'widgets' => [],
                        ],
                    ],
                ],
            ],
        ];
        if ($message instanceof OutgoingMessage) {
            $text = $message->getText();

            $payload['text'] = $text;

            $attachment = $message->getAttachment();
            if (!is_null($attachment) && $attachment instanceof Image) {
                $payload['cards'][0]['sections'][0]['widgets'][] = [
                    'image' => [
                        'imageUrl' => $attachment->getUrl(),
                    ],
                ];
            }
        } elseif ($message instanceof Question) {
            $payload['text'] = $message->getText();
            $buttons = $message->getButtons();
            if (!is_null($buttons)){
                foreach($buttons as $button){
                    if($button['image_url']){
                        $buttonarray = [
                            'textButton' => [
                                'text' => $button['text'],
                                'onClick' => [
                                    //if it's a link anyway....
                                    'openLink' => [
                                        'url' => (($button['image_url']) ? $button['image_url'] : 'http://notabug.io')
                                    ]
                                ]
                            ]
                        ];
                    }else{
                        $buttonarray = [
                            'textButton' => [
                                'text' => $button['text'],
                                'onClick' => [
                                    'action' => [
                                        'actionMethodName' => $button['text'],
                                        'parameters' => [
                                            [
                                                'key' => 'time',
                                                'value' => '1 day'
                                            ],[
                                                'key' => 'id',
                                                'value' => '42'
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ];
                    }
                    $buttonslist['buttons'][] = $buttonarray;
                }
                $payload['cards'][0]['sections'][0]['widgets'][] = $buttonslist;
            }
        }
        
        return $payload;
    }

    /**
     * @param mixed $payload
     *
     * @return Response
     */
    public function sendPayload($payload)
    {
        return JsonResponse::create($payload)->send();
    }

    /**
     * Low-level method to perform driver specific API requests.
     *
     * @param string                                           $endpoint
     * @param array                                            $parameters
     * @param \BotMan\BotMan\Messages\Incoming\IncomingMessage $matchingMessage
     *
     * @return void
     */
    public function sendRequest($endpoint, array $parameters, IncomingMessage $matchingMessage)
    {
        //
    }
}
