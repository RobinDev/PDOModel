<?php
namespace rOpenDev\PDOModel;

class Helper
{
    public static function slugify($str, $authorized = '[^a-z0-9/\.]')
    {
        $str = str_replace(array('\'', '"'), '', $str);
        if ($str !== mb_convert_encoding(mb_convert_encoding($str, 'UTF-32', 'UTF-8'), 'UTF-8', 'UTF-32')) {
            $str = mb_convert_encoding($str, 'UTF-8');
        }
        $str = htmlentities($str, ENT_NOQUOTES, 'UTF-8');
        $str = preg_replace('`&([a-z]{1,2})(acute|uml|circ|grave|ring|cedil|slash|tilde|caron|lig);`i', '$1', $str);
        $str = preg_replace(array('`'.$authorized.'`i', '`[-]+`'), '-', $str);

        return strtolower(trim($str, '-'));
    }

    public static function truncate($string, $max_length = 90, $replacement = '...', $trunc_at_space = false)
    {
        $max_length -= strlen($replacement);
        $string_length = strlen($string);
        if ($string_length <= $max_length) {
            return $string;
        }
        if ($trunc_at_space && ($space_position = strrpos($string, ' ', $max_length-$string_length))) {
            $max_length = $space_position;
        }

        return substr_replace($string, $replacement, $max_length);
    }
}
