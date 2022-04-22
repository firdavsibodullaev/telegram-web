<?php

namespace App\Telegram;

use App\Exceptions\ReplyToMessageException;
use App\Models\Admin;
use App\Models\ComplaintAdmin;
use App\Modules\Telegram\Exceptions\CallbackQueryException;
use App\Modules\Telegram\Exceptions\ChannelException;
use App\Modules\Telegram\Exceptions\FileException;
use App\Modules\Telegram\Exceptions\MessageException;
use App\Modules\Telegram\Exceptions\MyChatMemberException;
use App\Modules\Telegram\Telegram;
use App\Modules\Telegram\WebhookUpdates;
use App\Telegram\Dialog\Admins\AdminActions;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileCannotBeAdded;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;

class GroupChats
{
    /**
     * @var WebhookUpdates
     */
    public $updates;
    /**
     * @var Telegram
     */
    public $telegram;

    public function __construct(Telegram $telegram, WebhookUpdates $updates)
    {
        $this->telegram = $telegram;
        $this->updates = $updates;
    }

    /**
     * @return int
     * @throws CallbackQueryException
     * @throws FileCannotBeAdded
     * @throws FileDoesNotExist
     * @throws FileException
     * @throws FileIsTooBig
     * @throws MessageException
     * @throws MyChatMemberException
     * @throws ReplyToMessageException
     * @throws ChannelException
     */
    public function index(): int
    {
        $group = (int)config('services.telegram.group');
        if ($this->updates->chat() !== $group || !$this->updates->message()->isReplyToMessage()) {
            return 0;
        }

        $complaint = ComplaintAdmin::query()
            ->where('to_admin_message_id', '=', $this->updates->message()->replyToMessage()->getMessageId())
            ->exists();

        if (!Admin::query()->where('chat_id', '=', $this->updates->from())->exists()
            && $complaint) {
            $this->telegram->send('sendMessage', [
                'chat_id' => $this->updates->chat(),
                'text' => __('У вас нет доступа отвечать сообщениям'),
                'reply_to_message_id' => $this->updates->message()->getMessageId()
            ]);
            return 0;
        }

        if (Str::length($this->updates->text()) > 1024) {
            $this->telegram->send('sendMessage', [
                'chat_id' => $this->updates->chat(),
                'text' => __('Длинный текст, больше чем 1024 символов'),
                'reply_to_message_id' => $this->updates->message()->getMessageId()
            ]);
            return 0;
        }

        if ($this->updates->message()->isFile() && (is_null($file_size = $this->updates->message()->file()->size()) || $file_size > 1024 * 1024 * 20)) {
            $this->telegram->send('sendMessage', [
                'chat_id' => $this->updates->chat(),
                'text' => __('Нельзя загружать файл больше чем 20мб'),
                'reply_to_message_id' => $this->updates->message()->getMessageId()
            ]);
            return 0;
        }

        return (new AdminActions($this->telegram, $this->updates))->reply();
    }
}
