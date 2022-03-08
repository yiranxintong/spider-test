<?php

namespace App\Container;

abstract class StringUtils
{
    /**
     * strip and strip any BOMs if they exist.
     */
    public static function trim(string $string) : string
    {
        if ($string === '') {
            return '';
        }

        $result = $string;

        if (strpos($string, "\xef\xbb\xbf") === 0) {    // UTF-8 BOM
            $result = str_replace("\xef\xbb\xbf", '', $string);
        } elseif (in_array(substr($string, 0, 4), ["\xff\xfe\x00\x00", "\x00\x00\xfe\xff"], true)) { // UTF-32 BOM
            $result = substr($string, 4);
        } elseif (in_array(substr($string, 0, 2), ["\xff\xfe", "\xfe\xff"], true)) {    // UTF-16 BOM
            $result = substr($string, 2);
        }

        return trim($result);
    }

    public static function mask(string $message, string $mask = '******', bool $both = true) : string
    {
        $result = iconv_substr($message, 0, 1) . $mask;

        if ($both) {
            $result .= iconv_substr($message, -1, 1);
        }

        return $result;
    }
}
