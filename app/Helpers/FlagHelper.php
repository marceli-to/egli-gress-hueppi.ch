<?php

namespace App\Helpers;

class FlagHelper
{
    /**
     * Convert country code to flag emoji
     */
    public static function toEmoji(string $code): string
    {
        $code = strtolower($code);

        // Special cases for UK nations
        return match ($code) {
            'gb-eng' => '🏴󠁧󠁢󠁥󠁮󠁧󠁿',
            'gb-wls' => '🏴󠁧󠁢󠁷󠁬󠁳󠁿',
            'gb-sct' => '🏴󠁧󠁢󠁳󠁣󠁴󠁿',
            default => self::codeToFlag($code),
        };
    }

    /**
     * Convert 2-letter ISO code to flag emoji using regional indicators
     */
    private static function codeToFlag(string $code): string
    {
        // Regional indicator symbols start at U+1F1E6 for 'A'
        $code = strtoupper($code);

        if (strlen($code) !== 2) {
            return '';
        }

        $flag = '';
        for ($i = 0; $i < 2; $i++) {
            $char = ord($code[$i]);
            // A-Z is 65-90, regional indicators are 0x1F1E6-0x1F1FF
            if ($char >= 65 && $char <= 90) {
                $flag .= mb_chr(0x1F1E6 + ($char - 65));
            }
        }

        return $flag;
    }
}
