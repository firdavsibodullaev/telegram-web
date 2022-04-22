<?php

namespace App\Telegram;

use App\Modules\Telegram\BotUser;
use App\Modules\Telegram\Exceptions\CallbackQueryException;
use App\Modules\Telegram\Exceptions\MessageException;
use App\Modules\Telegram\Exceptions\MyChatMemberException;
use App\Modules\Telegram\Telegram;
use App\Modules\Telegram\WebhookUpdates;
use Exception;

class Index
{

    /**
     * @var Telegram
     */
    private $telegram;
    /**
     * @var WebhookUpdates
     */
    private $updates;

    public function __construct(Telegram $telegram, WebhookUpdates $updates)
    {
        $this->telegram = $telegram;
        $this->updates = $updates;
    }

    public function init()
    {
        $this->telegram->send('sendMessage', [
            'chat_id' => $this->updates->chat(),
            'text' => $this->updates->body()
        ]);
    }
}
