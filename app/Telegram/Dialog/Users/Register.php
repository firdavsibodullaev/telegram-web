<?php

namespace App\Telegram\Dialog\Users;

use App\Constants\LanguageConstants;
use App\Constants\MethodConstants;
use App\Models\Admin;
use App\Modules\Telegram\Exceptions\ContactException;
use App\Modules\Telegram\Exceptions\MessageException;
use App\Modules\Telegram\ReplyMarkup;
use App\Modules\Telegram\Telegram;
use App\Modules\Telegram\WebhookUpdates;
use App\Telegram\Keyboards;
use App\Telegram\PrivateChats;

class Register extends PrivateChats
{
    public function __construct(Telegram $telegram, WebhookUpdates $updates)
    {
        parent::__construct($telegram, $updates);
        if (is_null($this->action()->sub_action)) {
            $this->action()->update([
                'sub_action' => MethodConstants::REGISTRATION_SEND_LANGUAGES_LIST
            ]);
        }
    }

    /**
     * @return int
     */
    public function index(): int
    {
        $method = $this->action()->sub_action;
        if (method_exists($this, $method)) {
            $this->$method();
        }
        return 0;
    }

    /**
     * @return int
     */
    public function sendLanguagesList(): int
    {
        $keyboard = new ReplyMarkup();
        $this->telegram->send('sendMessage', [
            'chat_id' => $this->chat_id,
            'text' => "Здравствуйте! Давайте для начала выберем язык обслуживания!"
                . PHP_EOL . PHP_EOL . "Assalomu alaykum! Keling, boshlanishiga xizmat ko‘rsatish tilini tanlaymiz!",
            'reply_markup' => $keyboard
                ->resizeKeyboard()
                ->oneTimeKeyboard()
                ->keyboard(Keyboards::languagesButtons())
        ]);

        $this->action()->update([
            'sub_action' => MethodConstants::REGISTRATION_GET_LANGUAGE_SEND_NAME_REQUEST
        ]);

        return 0;
    }

    /**
     * @return int
     */
    public function getLanguageSendNameRequest(): int
    {
        if (!in_array($this->text, LanguageConstants::translatedList())) {
            $keyboard = new ReplyMarkup();
            $this->telegram->send('sendMessage', [
                'chat_id' => $this->chat_id,
                'text' => "Здравствуйте! Давайте для начала выберем язык обслуживания!"
                    . PHP_EOL . PHP_EOL . "Assalomu alaykum! Keling, boshlanishiga xizmat ko‘rsatish tilini tanlaymiz!",
                'reply_markup' => $keyboard
                    ->oneTimeKeyboard()
                    ->resizeKeyboard()
                    ->keyboard(Keyboards::languagesButtons())
            ]);
            return 0;
        }
        $this->botUser()->fetchUser()->update([
            'lang' => $lang = LanguageConstants::list()[$this->text]
        ]);

        app()->setLocale($lang);
        $keyboard = new ReplyMarkup();
        $this->telegram->send('sendMessage', [
            'chat_id' => $this->chat_id,
            'text' => __("Ввод имени"),
            'reply_markup' => $keyboard->removeKeyboard()->keyboard()
        ]);

        $this->action()->update([
            'sub_action' => MethodConstants::REGISTRATION_GET_NAME_SEND_PHONE_REQUEST
        ]);
        return 0;
    }

    /**
     * @return int
     * @throws MessageException
     */
    public function getNameSendPhoneRequest(): int
    {
        if ($this->updates->message()->isFile() || in_array($this->text, LanguageConstants::translatedList())) {
            $this->telegram->send('sendMessage', [
                'chat_id' => $this->chat_id,
                'text' => __("Ввод имени")
            ]);

            return 0;
        }

        $this->botUser()->fetchUser()->update([
            'first_name' => $this->text
        ]);

        $keyboard = new ReplyMarkup();

        $this->telegram->send('sendMessage', [
            'chat_id' => $this->chat_id,
            'text' => __('Отправка номера'),
            'reply_markup' => $keyboard
                ->oneTimeKeyboard()
                ->resizeKeyboard()
                ->keyboard(Keyboards::phoneRequest())
        ]);

        $this->action()->update([
            'sub_action' => MethodConstants::REGISTRATION_GET_PHONE_FINISH_REGISTRATION
        ]);

        return 0;
    }

    /**
     * @return int
     * @throws MessageException
     * @throws ContactException
     */
    public function getPhoneFinishRegistration(): int
    {
        if (!$this->updates->message()->isContact()) {
            $keyboard = new ReplyMarkup();
            $this->telegram->send('sendMessage', [
                'chat_id' => $this->chat_id,
                'text' => __('Отправка номера'),
                'reply_markup' => $keyboard->resizeKeyboard()->keyboard(Keyboards::phoneRequest())
            ]);

            return 0;
        }

        $phone = $this->updates->message()->contact()->phoneNumber();
        $bot_user = $this->botUser()->fetchUser();
        $bot_user->update([
            'admin' => $is_admin = in_array($this->chat_id, bot_admins()),
            'phone' => $phone[0] === '+' ? $phone : "+{$phone}",
            'status' => true
        ]);

        if ($is_admin) {
            Admin::query()->insert(['chat_id' => $this->chat_id]);
            $keyboard = new ReplyMarkup();
            $this->telegram->send('sendMessage', [
                'chat_id' => $this->chat_id,
                'text' => __("Добро пожаловать уважаемый(-ая) {$bot_user->first_name}"),
                'reply_markup' => $keyboard->removeKeyboard()->keyboard()
            ]);

            $this->action()->update([
                'action' => null,
                'sub_action' => null
            ]);

            return 0;
        }

        return $this->sendMainMenu();
    }
}
