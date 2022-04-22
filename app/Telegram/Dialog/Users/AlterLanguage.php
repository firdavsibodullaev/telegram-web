<?php

namespace App\Telegram\Dialog\Users;

use App\Constants\LanguageConstants;
use App\Constants\SubActionConstants;
use App\Modules\Telegram\ReplyMarkup;
use App\Modules\Telegram\Telegram;
use App\Modules\Telegram\WebhookUpdates;
use App\Telegram\Keyboards;
use App\Telegram\PrivateChats;

class AlterLanguage extends PrivateChats
{
    public function __construct(Telegram $telegram, WebhookUpdates $updates)
    {
        parent::__construct($telegram, $updates);

        if (is_null($this->action()->sub_action)) {
            $this->action()->update([
                'sub_action' => SubActionConstants::ALTER_LANGUAGE_SEND_LANGUAGE_LIST
            ]);
        }
    }

    public function index(): int
    {
        $method = $this->action()->sub_action;
        if (method_exists($this, $method)) {
            $this->$method();
        }
        return 0;
    }

    public function sendLanguageList()
    {
        $this->action()->update([
            'sub_action' => SubActionConstants::ALTER_LANGUAGE_GET_LANGUAGE
        ]);
        $keyboard = new ReplyMarkup();
        $this->telegram->send('sendMessage', [
            'chat_id' => $this->chat_id,
            'text' => __("Выберите язык"),
            'reply_markup' => $keyboard->resizeKeyboard()->keyboard(Keyboards::languagesButtons(true))
        ]);
    }

    public function getLanguage()
    {
        $lang = str_replace(' ✅', '', $this->text);

        if (!in_array($lang, LanguageConstants::translatedList())) {
            $this->sendLanguageList();
            return 0;
        }

        $this->botUser()->fetchUser()->update([
            'lang' => $lang = LanguageConstants::list()[$lang]
        ]);

        app()->setLocale($lang);
        $this->sendMainMenu();
    }
}
