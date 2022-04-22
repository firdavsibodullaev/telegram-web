<?php


namespace App\Modules\Telegram;


class ReplyMarkup
{
    /**
     * @var bool $one_time_keyboard
     */
    private $one_time_keyboard = false;

    /**
     * @var bool $resize_keyboard
     */
    private $resize_keyboard = false;

    /**
     * @var bool $is_inline
     */
    private $is_inline = false;

    /**
     * @var bool $is_keyboard
     */
    private $is_keyboard = true;

    /**
     * @var bool $is_remove_keyboard
     */
    private $is_remove_keyboard = false;

    /**
     * @var boolean $is_force_reply
     */
    private $is_force_reply = false;

    /**
     * @var array $keyboard
     */
    private $keyboard = [];

    /**
     * @var string[] $keyboard_types
     */
    private $keyboard_types = [
        'inline_keyboard',
        'keyboard',
        'force_reply',
        'remove_keyboard'
    ];

    /**
     * @param array $keyboard
     * @return false|string
     */
    public function keyboard(array $keyboard = [])
    {
        return json_encode($this->getKeyboard($keyboard));
    }

    /**
     * @return $this
     */
    public function inline(): ReplyMarkup
    {
        $this->is_inline = true;
        $this->is_keyboard = false;

        return $this;
    }

    /**
     * @return $this
     */
    public function removeKeyboard(): ReplyMarkup
    {
        $this->is_remove_keyboard = true;
        $this->is_keyboard = false;
        return $this;
    }

    /**
     * @return $this
     */
    public function forceKeyboard(): ReplyMarkup
    {
        $this->is_force_reply = true;
        $this->is_keyboard = false;
        return $this;
    }

    /**
     * @return $this
     */
    public function oneTimeKeyboard(): ReplyMarkup
    {
        $this->one_time_keyboard = true;
        return $this;
    }

    /**
     * @return $this
     */
    public function resizeKeyboard(): ReplyMarkup
    {
        $this->resize_keyboard = true;
        return $this;
    }

    /**
     * @param array $keyboard
     * @return array|bool[]
     */
    private function getKeyboard(array $keyboard): array
    {
        $this->setKeyboardType($keyboard);

        return $this->keyboard;
    }

    /**
     * Set keyboard type (inline or not)
     * @param array $keyboard
     */
    private function setKeyboardType(array $keyboard)
    {
        if ($this->is_keyboard) {
            $this->keyboard['keyboard'] = $keyboard;
            $this->keyboard['one_time_keyboard'] = $this->one_time_keyboard;
            $this->keyboard['resize_keyboard'] = $this->resize_keyboard;
        }

        if ($this->is_inline) {
            $this->keyboard['inline_keyboard'] = $keyboard;
        }

        if ($this->is_remove_keyboard) {
            $this->keyboard['remove_keyboard'] = true;
        }

        if ($this->is_force_reply) {
            $this->keyboard['force_reply'] = true;
        }
    }
}
