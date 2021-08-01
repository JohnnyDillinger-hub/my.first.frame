<?php

if(!function_exists('mb_str_replace'))
{
    function mb_str_replace($needle, $tex_replace, $haystack)
    {
        // implode() - объединяет элементы массива в строку
        return implode($tex_replace, explode($needle, $haystack));
    }
}
