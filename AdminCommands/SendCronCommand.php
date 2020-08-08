<?php

namespace Longman\TelegramBot\Commands\AdminCommands;

use Longman\TelegramBot\Conversation;
use Longman\TelegramBot\Entities\Keyboard;
use Models\AdminCommand;
use Longman\TelegramBot\DB;
use Longman\TelegramBot\Request;
use PDO;
use PDOStatement;

class SendCronCommand extends AdminCommand {
    /**
     * @var string
     */
    protected $name = 'sendcron';

    /**
     * @var string
     */
    protected $description = 'Send Cron messages.';

    /**
     * @var string
     */
    protected $usage = '/sendcron';

    public function execute()
    {
        /** @var PDOStatement $pdoStatement */
        $pdoStatement = DB::getPdo()->query('
            SELECT `amocrm_user_id`, `chat_id`, `phone`
            FROM `amocrm_user`
            GROUP BY `amocrm_user_id`
            ORDER BY `id` DESC'
            , PDO::FETCH_ASSOC);
        $amocrmUsersAr = [];
        foreach ($pdoStatement as $row) {
            $amocrmUsersAr [(int) $row['amocrm_user_id']] = [
                'chat_id' => (int) $row ['chat_id'],
                'phone' => $row ['phone']
            ];
        }

        $pdoStatement = DB::getPdo()->query('
            SELECT *
            FROM `cron_message`
            WHERE `status` = ' . self::STATUS_TO_SEND
            , PDO::FETCH_ASSOC);
        $messages = [];
        foreach ($pdoStatement as $row) {
            if (in_array((int) $row ['amocrm_user_id'], $amocrmUsersAr)) {
                $messages [(int) $row['id']] = [
                    'amocrm_user_id' => (int) $row ['amocrm_user_id'],
                    'amocrm_lead_id' => (int) $row ['amocrm_lead_id'],
                    'amocrm_status_id' => (int) $row ['amocrm_status_id'],
                    'chat_id' => $amocrmUsersAr [(int) $row ['amocrm_user_id']] ['chat_id'],
                    'type' => $row ['type'],
                    'phone' => $amocrmUsersAr [(int) $row ['amocrm_user_id']] ['phone'],
                ];
            }
        }

        $sentMessages = [];
        foreach ($messages as $id => $message) {
            switch ($message ['type']) {
                case self::REMIND_NO_ORDER:
                    $text = require TEMPLATE_PATH . DIRECTORY_SEPARATOR . 'remind.php';
                    $data = [
                        'chat_id' => $message ['chat_id'],
                        'text'    => $text,
                    ];
                    Request::sendMessage($data);

                    $sentMessages [] = $id;
                    break;
                case self::BILL_SENT:
                    $text = require TEMPLATE_PATH . DIRECTORY_SEPARATOR . 'billsent.php';
                    $data = [
                        'chat_id' => $message ['chat_id'],
                        'text'    => $text,
                    ];
                    Request::sendMessage($data);

                    $sentMessages [] = $id;
                    break;
                case self::SURVEY_FEEDBACK:
                    //Preparing Response
                    $msg = $this->getMessage();
                    $text    = trim($msg->getText(true));
                    $data = [
                        'chat_id' => $message ['chat_id'],
                    ];
                    $question = require TEMPLATE_PATH . DIRECTORY_SEPARATOR . 'surveysuccess.php';
                    $answers = ['1', '2', '3', '4', '5'];

                    //Conversation start
                    $this->conversation = new Conversation($message ['chat_id'], $message ['chat_id'], $this->getName());

                    // $result = Request::emptyResponse();
                    if ($text === '' || !in_array($text, $answers, true)) {
                        $this->conversation->update();

                        $data['reply_markup'] = (new Keyboard($answers))
                            ->setResizeKeyboard(true)
                            ->setOneTimeKeyboard(true)
                            ->setSelective(true);

                        $data ['text'] = $question;
                        if ($text !== '') {
                            $data ['text'] = $question;
                        }

                        Request::sendMessage($data);
                    }

                    $this->conversation->stop();

                    $data = [
                        'reply_markup' => Keyboard::remove(['selective' => true]),
                        'chat_id' => $message ['chat_id'],
                        'text' => 'Спасибо за обратную связь, Вы выбрали ' . $text,
                    ];
                    Request::sendMessage($data);


                    $sentMessages [] = $id;
                    break;
                case self::SURVEY_NOT_BOUGHT:
                    $sentMessages [] = $id;
                    break;
            }
        }

        if (!empty($sentMessages)) {
            $sth = DB::getPdo()->prepare('
                UPDATE `cron_message` SET 
                    `status` = ' . self::STATUS_SENT . ',
                    `updated_at` = NOW()
                WHERE `id` IN (' . implode(',', $sentMessages). ')
            ');
            $sth->execute($sth);
        }

    }
}