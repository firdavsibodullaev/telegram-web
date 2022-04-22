<?php

namespace App\Telegram;

use App\Constants\LanguageConstants;
use App\Constants\MainButtonConstants;

class Keyboards
{

    /**
     * @return \array[][]
     */
    public static function mainButtons(): array
    {
        return [
            [
                [
                    'text' => __(MainButtonConstants::TECHNICAL_PROBLEM),
                ],
            ],
            [
                [
                    'text' => __(MainButtonConstants::ADDRESS_NOT_FOUND),
                ],
                [
                    'text' => __(MainButtonConstants::IMPROVE_IDEAS),
                ],
            ],
            [
                [
                    'text' => __(MainButtonConstants::TESTER),
                ],
                [
                    'text' => __(MainButtonConstants::ALTER_LANGUAGE),
                ],
            ],
            [
                [
                    'text' => __(MainButtonConstants::COMPLAINT_PROBLEM_SOLVING),
                ],
            ],
        ];
    }

    /**
     * @return \array[][]
     */
    public static function languagesButtons(bool $is_edit = false): array
    {
        $lang = app()->getLocale();
        return [
            [
                [
                    'text' => __(LanguageConstants::UZ) . ($is_edit && $lang === LanguageConstants::UZ ? ' ✅' : ''),
                ],
                [
                    'text' => __(LanguageConstants::OZ) . ($is_edit && $lang === LanguageConstants::OZ ? ' ✅' : ''),
                ],
                [
                    'text' => __(LanguageConstants::RU) . ($is_edit && $lang === LanguageConstants::RU ? ' ✅' : ''),
                ],
            ],
        ];
    }

    /**
     * @return \array[][]
     */
    public static function phoneRequest(): array
    {
        return [
            [
                [
                    'text' => __("Поделиться номером"),
                    'request_contact' => true,
                ]
            ]
        ];
    }

    /**
     * @return array
     */
    public static function sendRequestOrReturnBack(): array
    {
        return [
            [
                [
                    'text' => __("Отправить"),
                ]
            ],
            [
                [
                    'text' => __("Назад"),
                ]
            ]
        ];
    }
}
