<?php

namespace App\Telegram\Dialog\Users;

use App\Constants\ActionKeyConstants;
use App\Constants\SubActionConstants;
use App\Models\BotUser;
use App\Models\Chat;
use App\Models\Complaint;
use App\Models\ComplaintAdmin;
use App\Modules\Telegram\Exceptions\FileException;
use App\Modules\Telegram\Exceptions\MessageException;
use App\Modules\Telegram\File\Methods;
use App\Modules\Telegram\ReplyMarkup;
use App\Modules\Telegram\Telegram;
use App\Modules\Telegram\WebhookUpdates;
use App\Telegram\Keyboards;
use App\Telegram\PrivateChats;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileCannotBeAdded;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;

class Feedback extends PrivateChats
{

    public function __construct(Telegram $telegram, WebhookUpdates $updates)
    {
        parent::__construct($telegram, $updates);

        $action = $this->action();

        if (!$action->sub_action) {

            $action->update([
                'sub_action' => SubActionConstants::FEEDBACK_ENTER_TEXT
            ]);

            $this->notSentComplaintsBuilder()->delete();

            $this->chat()->update([
                'action' => ActionKeyConstants::action($this->text)
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

    public function enterText()
    {
        $keyboard = new ReplyMarkup();
        $this->telegram->send('sendMessage', [
            'chat_id' => $this->chat_id,
            'text' => ActionKeyConstants::texts($this->chat()->action),
            'reply_markup' => $keyboard
                ->resizeKeyboard()
                ->keyboard(Keyboards::sendRequestOrReturnBack())
        ]);

        $this->action()->update(['sub_action' => SubActionConstants::FEEDBACK_GET_TEXT]);


        $this->botUser()->fetchUser()->update([
            'last_event_time' => time()
        ]);

    }

    /**
     * @return int
     * @throws FileCannotBeAdded
     * @throws FileDoesNotExist
     * @throws FileException
     * @throws FileIsTooBig
     * @throws MessageException
     */
    public function getText(): int
    {
        $user = $this->botUser()->fetchUser();

        if (time() - $user->last_event_time > 60 * 60 * 24) {
            $this->telegram->send('sendMessage', [
                'chat_id' => $this->chat_id,
                'text' => __('Время просрочено, отправьте заново')
            ]);
            return $this->sendMainMenu();
        }

        if ($this->text === __('Назад')) {
            return $this->sendMainMenu();
        }

        if ($this->text === __('Отправить')) {
            $this->sendMessages();
            return 0;
        }

        $this->saveComplaint();

        return 0;
    }

    /**
     * @return int
     * @throws FileException
     * @throws MessageException
     * @throws FileCannotBeAdded
     * @throws FileDoesNotExist
     * @throws FileIsTooBig
     */
    protected function saveComplaint(): int
    {
        /** @var Complaint $complaint */
        $complaint = Complaint::query()->create([
            'chat_id' => $this->chat_id,
            'support_chat_id' => $this->chat()->id,
            'message_id' => $this->updates->message()->getMessageId(),
            'text' => $this->text,
            'action' => $this->chat()->action,
            'date' => $this->updates->message()->getDate()
        ]);

        if ($this->updates->message()->isFile()) {
            $file_id = $this->updates->message()->file()->fileId();
            $file = $this->telegram->getFile($file_id);
            $url = $this->telegram->getFilePathUrl($file->path());
            $complaint->addMediaFromUrl($url)->toMediaCollection()->setCustomProperty('file_id', $file_id)->save();
        }

        return 0;
    }

    /**
     * @return int
     */
    public function sendMessages(): int
    {
        $complaints = $this->notSentComplaintsBuilder()->with('firstMedia')->get();

        $group = (int)config('services.telegram.group');

        $admins = BotUser::query()->where([
            ['status', '=', 1],
            ['admin', '=', 1]
        ])->pluck('chat_id')->toArray();

        if ($group) {
            $admins[] = $group;
        }

        $method = 'sendMessage';
        $complaint_admin = [];
        /** @var Complaint $complaint */
        foreach ($complaints as $complaint) {
            $params = [];
            $text = $this->prepareText($complaint);
            if ($media = $complaint->firstMedia) {

                $mime = explode('/', $media->mime_type)[0];
                $methods = Methods::list($mime);
                $method = $methods['method'];
                $params[$methods['type']] = $media->getCustomProperty('file_id');
                $params['caption'] = $text;
            } else {
                $params['text'] = $text;
            }

            foreach ($admins as $admin) {
                $params['chat_id'] = $admin;
                $message = $this->telegram->send($method, $params);
                $complaint_admin[] = [
                    'message_id' => $complaint->message_id,
                    'to_admin_message_id' => $message['result']['message_id'],
                ];
            }

        }
        ComplaintAdmin::query()->insert($complaint_admin);

        $this->notSentComplaintsBuilder()->update(['sent' => true]);

        $this->telegram->send('sendMessage', [
            'chat_id' => $this->chat_id,
            'text' => __("Спасибо за ваш отзыв!")
        ]);

        return $this->sendMainMenu();
    }

    /**
     * @return Chat
     */
    protected function chat(): Chat
    {
        return Chat::firstOrCreate([
            'chat_id' => $this->chat_id,
        ]);
    }

    /**
     * @return Builder
     */
    protected function notSentComplaintsBuilder(): Builder
    {
        return Complaint::query()->where([
            ['chat_id', '=', $this->chat_id],
            ['sent', '=', false]
        ]);
    }

    /**
     * @param Complaint $complaint
     * @return string
     */
    protected function prepareText(Complaint $complaint): string
    {
        $bot_user = $this->botUser()->fetchUser();
        return "Сообщение от: {$bot_user->first_name}"
            . PHP_EOL . "Номер телефона: {$bot_user->phone}"
            . PHP_EOL . PHP_EOL . $complaint->text
            . PHP_EOL . PHP_EOL . ActionKeyConstants::hashtags()[$complaint->action];

    }
}
