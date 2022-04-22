<?php

namespace App\Telegram\Dialog\Admins;

use App\Constants\ActionKeyConstants;
use App\Exceptions\ReplyToMessageException;
use App\Models\Complaint;
use App\Models\ComplaintAdmin;
use App\Modules\Telegram\Exceptions\CallbackQueryException;
use App\Modules\Telegram\Exceptions\FileException;
use App\Modules\Telegram\Exceptions\MessageException;
use App\Modules\Telegram\Exceptions\MyChatMemberException;
use App\Modules\Telegram\File\Methods;
use App\Telegram\PrivateChats;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileCannotBeAdded;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;

class AdminActions extends PrivateChats
{

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
     */
    public function index(): int
    {
        if ($this->updates->message()->isReplyToMessage()) {
            $this->reply();
        }
        return 0;
    }

    /**
     * @return int
     * @throws FileCannotBeAdded
     * @throws FileDoesNotExist
     * @throws FileException
     * @throws FileIsTooBig
     * @throws MessageException
     * @throws ReplyToMessageException
     * @throws CallbackQueryException
     * @throws MyChatMemberException
     */
    public function reply(): int
    {
        $reply_message = $this->updates->message()->replyToMessage();

        $complaint = $this->complaintMessage($reply_message->getMessageId());

        if (is_null($complaint)) {
            return 0;
        }

        $answer = $this->saveAnswer($complaint);

        $method = 'sendMessage';
        $params = [
            'chat_id' => $complaint->chat_id,
            'text' => $this->text,
            'reply_to_message_id' => $complaint->message_id
        ];

        $this->attachFile($answer, $method, $params);

        $this->telegram->send($method, $params);
        $answer->update(['sent' => true]);

        if (!in_array($this->updates->from(), bot_admins())) {
            $this->notifySuperAdminAboutMessage($complaint, $answer);
        }

        return 0;
    }

    /**
     * @param Complaint $complaint
     * @return Complaint
     * @throws FileCannotBeAdded
     * @throws FileDoesNotExist
     * @throws FileException
     * @throws FileIsTooBig
     * @throws MessageException
     * @throws CallbackQueryException
     * @throws MyChatMemberException
     */
    protected function saveAnswer(Complaint $complaint): Complaint
    {
        /** @var Complaint $complaint */
        $complaint = Complaint::query()->create([
            'chat_id' => $this->updates->from(),
            'action' => 'answer',
            'support_chat_id' => $complaint->support_chat_id,
            'text' => $this->text,
            'complaint_id' => $complaint->id,
            'message_id' => $this->updates->message()->getMessageId(),
            'date' => $this->updates->message()->getDate(),
            'read' => 1
        ]);

        if ($this->updates->message()->isFile()) {
            $file_id = $this->updates->message()->file()->fileId();
            $path = $this->telegram->getFile($file_id);
            $url = $this->telegram->getFilePathUrl($path->path());
            $complaint->addMediaFromUrl($url)->toMediaCollection()->setCustomProperty('file_id', $file_id)->save();
        }

        return $complaint->load(['firstMedia', 'user']);
    }

    /**
     * @param Complaint $answer
     * @param string $method
     * @param array $params
     * @return void
     */
    protected function attachFile(Complaint $answer, string &$method, array &$params)
    {
        if ($media = $answer->firstMedia) {
            $mime = explode('/', $media->mime_type)[0];
            $methods = Methods::list($mime);
            $method = $methods['method'];
            $params[$methods['type']] = $media->getCustomProperty('file_id');
            $params['caption'] = $params['text'];
            unset($params['text']);
        }
    }

    /**
     * @param int $message_id
     * @return Complaint|null
     */
    protected function complaintMessage(int $message_id): ?Complaint
    {
        /** @var ComplaintAdmin $complaint */
        $complaint = ComplaintAdmin::query()
            ->where('to_admin_message_id', '=', $message_id)
            ->with('complaint')
            ->first();

        if (is_null($complaint)) {
            return null;
        }

        return $complaint->complaint->load('user');
    }

    /**
     * @param Complaint $complaint
     * @param Complaint $answer
     * @return void
     */
    protected function notifySuperAdminAboutMessage(Complaint $complaint, Complaint $answer)
    {
        $text = $this->prepareText($complaint, $answer);
        $admins = bot_admins();

        foreach ($admins as $admin) {
            $method = 'sendMessage';
            $params = [
                'chat_id' => $admin,
                'text' => $text,
            ];

            $this->attachFile($answer, $method, $params);


            $this->telegram->send($method, $params);
        }
    }

    /**
     * @param Complaint $complaint
     * @param Complaint $answer
     * @return string
     */
    protected function prepareText(Complaint $complaint, Complaint $answer): string
    {
        return "Ответил: {$answer->user->first_name}"
            . PHP_EOL . PHP_EOL . "Сообщение:"
            . PHP_EOL . '------------------------------------------------------------------'
            . PHP_EOL . "Сообщение от: {$complaint->user->first_name}"
            . PHP_EOL . "Номер телефона: {$complaint->user->phone}"
            . PHP_EOL . PHP_EOL . $complaint->text
            . PHP_EOL . PHP_EOL . ActionKeyConstants::hashtags()[$complaint->action]
            . PHP_EOL . PHP_EOL . "Ответ:"
            . PHP_EOL . '------------------------------------------------------------------'
            . PHP_EOL . PHP_EOL . $answer->text;
    }
}
