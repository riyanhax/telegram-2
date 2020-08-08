<?php

namespace Models;

use Longman\TelegramBot\Commands\UserCommand as UserCommandBase;
use Longman\TelegramBot\Conversation;
use Longman\TelegramBot\DB;

abstract class UserCommand extends UserCommandBase {
    /**
     * @var string
     */
    protected $version = '1.0.0';

    protected $notes;

    const TIMEZONE = 'Europe/Kiev';

    const PIPELINE_ID = 1979362;

    const STATUS_TO_SEND = 0;
    const STATUS_SENT = 1;

    const ERROR_AMOCRM = 'Ошибка при подключении к хранилищу.';
    const ERROR_PHONE_NOT_FOUND = 'Не найден телефон в базе.';
    const SUCCESS_LOGIN = 'Успешная авторизация.';

    const MENU_ORDER_STATUS = 'Статус заказа';
    const MENU_HISTORY = 'История заказов';
    const MENU_CATALOG = 'Каталог';


    protected function getState() {
        //Conversation start
        $this->conversation = new Conversation($this->user_id, $this->chat_id, $this->getName());

        $this->notes = &$this->conversation->notes;
        !is_array($this->notes) && $this->notes = [];

        //cache data from the tracking session if any
        $state = 0;
        if (isset($notes ['state'])) {
            $state = $notes ['state'];
        }

        return $state;
    }

    protected function prepareInput() {
        $message = $this->getMessage();
        $chat    = $message->getChat();
        $user    = $message->getFrom();

        $this->chat_id = $chat->getId();
        $this->user_id = $user->getId();
        $this->text = trim($message->getText(true));
    }

    protected function processPhones($phonesAr) {
        $phonesEscapedAr = [];
        if (!empty($phonesAr)) {
            foreach ($phonesAr as $phoneNum) {
                $phonesEscapedAr [] = substr(preg_replace('/[^0-9+]/', '', $phoneNum), -10);
            }
        }
        if (empty($phonesEscapedAr)) {
            return '';
        }
        return implode(',', $phonesEscapedAr);
    }

    protected function checkInsertUser($phone, $contactId) {
        // Update table
        if ($this->user_id == $this->chat_id) { // Private chat
            /** @var \PDOStatement $pdoStatement */
            $sth = DB::getPdo()->prepare('
                                    SELECT `phone`
                                    FROM `amocrm_user`
                                    WHERE `chat_id` = :chat_id AND `amocrm_user_id` = :amocrm_user_id
                                    ORDER BY `id` DESC
                                    LIMIT 1'
            );
            $sth->execute([
                ':chat_id' => $this->chat_id,
                ':amocrm_user_id' => $contactId,
            ]);
            $exist = $sth->fetch(\PDO::FETCH_ASSOC);
            if (empty($exist) || $phone != $exist ['phone']) {
                $sth = DB::getPdo()->prepare('
                                        INSERT INTO `amocrm_user` SET
                                        `chat_id` = :chat_id, 
                                        `amocrm_user_id` = :amocrm_user_id,
                                        `phone` = :phone
                                    ');
                $sth->execute([
                    ':chat_id' => $this->chat_id,
                    ':amocrm_user_id' => $contactId,
                    ':phone' => $phone,
                ]);
            }
        }
    }
}