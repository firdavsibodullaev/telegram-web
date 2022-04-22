<?php

namespace App\Telegram;

use App\Constants\ActionConstants;
use App\Constants\MainButtonConstants;
use App\Exceptions\ReplyToMessageException;
use App\Models\Action;
use App\Modules\Telegram\BotUser;
use App\Modules\Telegram\Exceptions\FileException;
use App\Modules\Telegram\Exceptions\MessageException;
use App\Modules\Telegram\ReplyMarkup;
use App\Modules\Telegram\Telegram;
use App\Modules\Telegram\WebhookUpdates;
use App\Telegram\Dialog\Admins\AdminActions;
use App\Telegram\Dialog\Users\Register;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileCannotBeAdded;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;

class PrivateChats
{
    /**
     * @var WebhookUpdates
     */
    public $updates;
    /**
     * @var Telegram
     */
    public $telegram;
    /**
     * @var int|null
     */
    public $chat_id;
    /**
     * @var string|null
     */
    public $text;

    public function __construct(Telegram $telegram, WebhookUpdates $updates)
    {
        $this->telegram = $telegram;
        $this->updates = $updates;
        $this->chat_id = $this->updates->chat();
        $this->text = $this->updates->text();
    }

    /**
     * @return int
     * @throws ReplyToMessageException
     * @throws FileException
     * @throws MessageException
     * @throws FileCannotBeAdded
     * @throws FileDoesNotExist
     * @throws FileIsTooBig
     */
    public function index(): int
    {

        if (Str::length($this->text) > 1024) {
            $this->telegram->send('sendMessage', [
                'chat_id' => $this->chat_id,
                'text' => __('Длинный текст, больше чем 1024 символов')
            ]);
            return 0;
        }

        if ($this->updates->message()->isFile() && (is_null($file_size = $this->updates->message()->file()->size()) || $file_size > 1024 * 1024 * 20)) {
            $this->telegram->send('sendMessage', [
                'chat_id' => $this->chat_id,
                'text' => __('Нельзя загружать файл больше чем 20мб')
            ]);
            return 0;
        }

        if (!$this->botUser()->isRegistrationFinished()) {
            if ($this->text === '/start') {
                $this->action()->update([
                    'action' => Register::class,
                    'sub_action' => null,
                ]);
            }

            return (new Register($this->telegram, $this->updates))->index();
        }

        if ($this->text === '/start') {

            if ($this->botUser()->fetchUser()->admin) {
                $this->action()->update([
                    'action' => null,
                    'sub_action' => null
                ]);

                $keyboard = new ReplyMarkup();
                $this->telegram->send('sendMessage', [
                    'chat_id' => $this->chat_id,
                    'text' => __("Добро пожаловать уважаемый(-ая) {$this->botUser()->fetchUser()->first_name}"),
                    'reply_markup' => $keyboard->removeKeyboard()->keyboard()
                ]);

                return 0;
            }

            $this->telegram->send('sendMessage', [
                'chat_id' => $this->chat_id,
                'text' => __("Здравствуйте дорогой пользователь")
            ]);
            return $this->sendMainMenu();
        }


        if ($this->botUser()->fetchUser()->admin) {
            (new AdminActions($this->telegram, $this->updates))->index();
            return 0;
        }

        return $this->setAction();
    }

    /**
     * @return BotUser
     */
    public function botUser(): BotUser
    {
        return new BotUser($this->chat_id);
    }

    /**
     * @return Action
     */
    public function action(): Action
    {
        return Action::firstOrCreate([
            'chat_id' => $this->chat_id
        ]);
    }

    /**
     * @return int
     */
    public function sendMainMenu(): int
    {

        $this->action()->update([
            'action' => null,
            'sub_action' => null
        ]);

        $keyboard = new ReplyMarkup();
        $this->telegram->send('sendMessage', [
            'chat_id' => $this->chat_id,
            'text' => __("Меню"),
            'reply_markup' => $keyboard->resizeKeyboard()->keyboard(Keyboards::mainButtons())
        ]);

        return 0;
    }

    /**
     * @return int
     */
    public function setAction(): int
    {
        $action = $this->action();
        $class = null;
        if (in_array($this->text, MainButtonConstants::list())) {
            $action->update([
                'action' => $class = ActionConstants::actions($this->text),
                'sub_action' => null
            ]);
        }

        if (!$class && !$action->action) {
            $this->telegram->send('sendMessage', [
                'chat_id' => $this->chat_id,
                'text' => __("Выберите категорию")
            ]);

            return $this->sendMainMenu();
        }

        if ($class) {
            (new $class($this->telegram, $this->updates))->index();

            return 0;
        }

        $class = $action->action;

        (new $class($this->telegram, $this->updates))->index();

        return 0;
    }
}
