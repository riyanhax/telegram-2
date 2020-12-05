<?php

namespace Models;

use Longman\TelegramBot\Commands\UserCommand as UserCommandBase;
use Longman\TelegramBot\DB;

abstract class UserCommand extends UserCommandBase {
    use CommandTrait;

    const ERROR_AMOCRM = 'Ошибка при подключении к хранилищу.';
    const SUCCESS_LOGIN = 'Успешная авторизация.';
    const LEADS_NOT_FOUND = 'Сделок не найдено.';

    const MENU_ORDER_STATUS = 'Статус заказа';
    const MENU_HISTORY = 'История заказов';
    const MENU_CATALOG = 'Каталог';
    const MENU_NEWS_CHANNEL = 'Рассказывать о новостях';

    const MENU_REQUIRE_CALL = 'Заказать обратный звонок';

    /**
     * @var bool
     */
    protected $need_mysql = true;

    protected function getAmocrmUserIdByPhone($phone) {
        $exist = Logic::getAmocrmUsers([
            'fields' => ['id', 'amocrm_user_id'],
            'filters' => [
                'chat_id' => $this->chat_id,
                'phone' => $phone,
                'limit' => 1,
            ]
        ]);

        if (!empty($exist) && !empty($exist[0]) && !empty($exist[0]['id'])) {
            $currentDateTime = BasePdo::now();
            $updateSuccess = Logic::updateAmocrmUser([
                'updated_at' => $currentDateTime
            ], [
                'id' => $exist[0]['id'],
            ]);

            return $updateSuccess;
        }

        return false;
    }

    protected function checkInsertUser($phone, $amocrmUserId) {
        // Update table
        if ($this->user_id == $this->chat_id) { // Private chat
            $currentDateTime = date('Y-m-d H:i:s');
            if (null === $amocrmUserId) {
                // Contact in AMOCRM not found
                $sth = DB::getPdo()->prepare('
                                    SELECT `id`
                                    FROM `amocrm_user`
                                    WHERE `phone` = :phone
                                    ORDER BY `id` DESC
                                    LIMIT 1'
                );

                $sth->execute([
                    ':phone' => $phone,
                ]);
                $exist = $sth->fetch(\PDO::FETCH_ASSOC);
                if (empty($exist)) {
                    $this->insertUser(null, $phone);
                } else {
                    $sth = DB::getPdo()->prepare('
                                        UPDATE `amocrm_user` SET
                                        `chat_id` = :chat_id,
                                        `amocrm_user_id` = :amocrm_user_id,
                                        `updated_at` = :current_date_time
                                        WHERE `id` = :id
                                    ');
                    $sth->execute([
                        ':id' => $exist ['id'],
                        ':chat_id' => $this->chat_id,
                        ':amocrm_user_id' => null,
                        ':current_date_time' => $currentDateTime
                    ]);
                }
            } else {
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
                    ':amocrm_user_id' => $amocrmUserId,
                ]);
                $exist = $sth->fetch(\PDO::FETCH_ASSOC);
                if (empty($exist) || $phone != $exist ['phone']) {
                    $this->insertUser($amocrmUserId, $phone);
                }
            }
        }
    }

    protected function insertUser($amocrmUserId, $phone) {
        $currentDateTime = date('Y-m-d H:i:s');

        $sth = DB::getPdo()->prepare('
                                            INSERT INTO `amocrm_user` SET
                                            `chat_id` = :chat_id, 
                                            `amocrm_user_id` = :amocrm_user_id,
                                            `phone` = :phone,
                                            `created_at` = :current_date_time,
                                            `updated_at` = :current_date_time
                                        ');
        $sth->execute([
            ':chat_id' => $this->chat_id,
            ':amocrm_user_id' => $amocrmUserId,
            ':phone' => $phone,
            ':current_date_time' => $currentDateTime
        ]);
    }

    protected function getContactIdByChatId($chatId) {
        $amocrmUser = Queries::getAmocrmUserByChatId($chatId, 'amocrm_user_id');
        if (!empty($amocrmUser) && !empty($amocrmUser ['amocrm_user_id'])) {
            return $amocrmUser ['amocrm_user_id'];
        }

        return false;
    }
}