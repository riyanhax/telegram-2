<?php
/**
 * This file is part of the TelegramBot package.
 *
 * (c) Avtandil Kikabidze aka LONGMAN <akalongman@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Longman\TelegramBot\Commands\UserCommands;

use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Conversation;
use Longman\TelegramBot\Entities\Keyboard;
use Longman\TelegramBot\Entities\KeyboardButton;
use Longman\TelegramBot\Entities\PhotoSize;
use Longman\TelegramBot\Request;
use Models\AdminCommand;

/**
 * Admin "/surveysuccess" command
 *
 * Command that demonstrated the Conversation funtionality in form of a simple survey.
 */
class SurveySuccessCommand extends AdminCommand
{
    /**
     * @var string
     */
    protected $name = 'surveysuccess';

    /**
     * @var string
     */
    protected $description = 'Survey for success buy.';

    /**
     * @var string
     */
    protected $usage = '/surveysuccess';

    /**
     * @var string
     */
    protected $version = '0.3.0';

    /**
     * @var bool
     */
    protected $need_mysql = true;

    /**
     * @var bool
     */
    protected $private_only = true;

    /**
     * Conversation Object
     *
     * @var \Longman\TelegramBot\Conversation
     */
    protected $conversation;

    /**
     * Command execute method
     *
     * @return \Longman\TelegramBot\Entities\ServerResponse
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    public function execute()
    {
        $msg = $this->getMessage();

        $chat    = $msg->getChat();
        $user    = $msg->getFrom();
        $text    = trim($msg->getText(true));
        $chat_id = $chat->getId();
        $user_id = $user->getId();

        //Conversation start
        $this->conversation = new Conversation($user_id, $chat_id, $this->getName());

        $notes = &$this->conversation->notes;
        !is_array($notes) && $notes = [];

        //cache data from the tracking session if any
        $state = 0;
        if (isset($notes['state'])) {
            $state = $notes['state'];
        }

        $result = Request::emptyResponse();

        switch ($state) {
            case 0:
                // $result = Request::emptyResponse();
                $question = require TEMPLATE_PATH . DIRECTORY_SEPARATOR . 'surveyfail.php';

                if ($text === '') {
                    $this->conversation->update();

                    $data ['text'] = $question;
                    Request::sendMessage($data);
                }

                $notes ['surveyfail'] = $text;
                $text = '';

            case 1:
                if ($text === '') {
                    $notes ['state'] = 1;
                    $this->conversation->update();
                    $this->conversation->stop();

                    $data = [
                        'text' => 'Спасибо за обратную связь!',
                    ];
                    $result = Request::sendMessage($data);
                }
                break;
        }

        return $result;
    }
}