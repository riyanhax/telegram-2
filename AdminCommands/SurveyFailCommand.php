<?php

namespace Longman\TelegramBot\Commands\AdminCommands;

use DrillCoder\AmoCRM_Wrap\AmoCRM;
use DrillCoder\AmoCRM_Wrap\AmoWrapException;
use Longman\TelegramBot\TelegramLog;
use Longman\TelegramBot\Commands\AdminCommand;
use Longman\TelegramBot\DB;
use Longman\TelegramBot\Request;
use PDO;
use PDOStatement;

class SurveyFailCommand extends AdminCommand {
    /**
     * @var string
     */
    protected $name = 'surveyfail';

    /**
     * @var string
     */
    protected $description = 'Survey for user after fail Order.';

    /**
     * @var string
     */
    protected $usage = '/surveyfail';

    /**
     * @var string
     */
    protected $version = '1.0.0';

    const REMIND_NO_ORDER = 'remind_no_order';
    const BILL_SENT = 'bill_sent';
    const SURVEY_FEEDBACK = 'survey_feedback';
    const SURVEY_NOT_BOUGHT = 'survey_not_bought';

    const STATUSES = [
        29361424    => 'Неразобранное',
        29361427    => 'Новое обращение',
        29361430    => 'Заказ Согласован',
        29361433    => 'Договор/счет отправлен',
        29399374    => 'Передан на склад',
        29548384    => 'Заказ Списан',
        31654648    => 'заказ собран с дефицитом',
        30617692    => 'заказ собран без дефицита',
        29362315    => 'Товар отгружен',
        29362318    => 'НЕзавершенные',
        142         => 'Успешно реализовано',
        143         => 'Закрыто и не реализовано',
    ];

    const TIMEZONE = 'Europe/Kiev';

    public function execute()
    {
        $message = $this->getMessage();
        $text    = trim($message->getText(true));

        /** @var PDOStatement $pdoStatement */
        $pdoStatement = DB::getPdo()->query('SELECT `user_id`, `text` FROM `message` WHERE `chat_id` = `user_id` AND `entities` LIKE \'%"length":10,"type":"phone_number"%\'', PDO::FETCH_ASSOC);
        $resultAr = [];
        foreach ($pdoStatement as $row) {
            $resultAr [$row['user_id']] = $row ['text'];
        }

        try {
            $amo = new AmoCRM(getenv('AMOCRM_DOMAIN'), getenv('AMOCRM_USER_EMAIL'), getenv('AMOCRM_USER_HASH'));

            $pipelineId = 1979362; // id Воронки
            $statusId = 143; // id Статуса: Закрыто и не реализовано

            /** @var \DateTime $startSearch */
            $startSearch = new \DateTime(date('Y-m-d 00:00:00'), new \DateTimeZone(self::TIMEZONE));
            // Yesterday from 00:00, one Survey for each day
            $startSearch->modify('-1 days');

            $endSearch = new \DateTime(date('Y-m-d 23:59:59'), new \DateTimeZone(self::TIMEZONE));
            $endSearch->modify('-1 days');

            /** @var \DrillCoder\AmoCRM_Wrap\Lead[] $leads */
            $leads = $amo->searchLeads(null, $pipelineId, [$statusId], 0, 0, [], $startSearch);

            /** @inherited $lead */
            $leadsAr = [];
            foreach ($leads as $lead) {
                // Cut today results
                if ($lead->getDateUpdate() <= $endSearch) {
                    // Get last updated Lead for User
                    if (
                        empty($leadsAr [$lead->getMainContactId()]) ||
                        $lead->getDateUpdate() >
                        $leadsAr [$lead->getMainContactId()] ['updated_at']
                    ) {
                        $leadsAr [$lead->getMainContactId()] = [
                            'lead_id' => $lead->getId(),
                            'updated_at' => $lead->getDateUpdate(),
                            'phones' => $lead->getPhones(),
                            'status_id' => $lead->getStatusId(),
                            'user_id' => $lead->getMainContactId(),
                        ];
                    }
                }


                // $leadsAr[] = $lead->getId() . ', ' . $lead->getName() . ': ' . implode(',', $lead->getMainContact()->getPhones());
            }

            TelegramLog::notice(sizeof($leadsAr) . PHP_EOL . PHP_EOL);

            foreach ($leadsAr as $userId => $leadAr) {
                TelegramLog::notice($userId . ' : ' . var_export($leadAr, true) . PHP_EOL);
            }


        } catch (AmoWrapException $e) {
            TelegramLog::error($e->getMessage());
        }


        /*echo implode('<br />', $leadsAr);

        foreach ($resultAr as $chat_id => $phone) {
            $data = [
                'chat_id' => getenv('CHANNEL_CHAT_ID'),
                'text' => 'Доброй ночи, дорогие клиенты!',
            ];
        }


        $data = [
            'chat_id' => getenv('CHANNEL_CHAT_ID'),
            'text' => $text,
        ];

        Request::sendMessage($data);*/
    }
}