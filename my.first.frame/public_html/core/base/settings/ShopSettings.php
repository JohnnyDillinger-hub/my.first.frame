<?php


namespace core\base\settings;

class ShopSettings
{

    use BaseSettings;

    static private $_instance;

    // путь к расширеням плагинов
    private $expansion = 'core/plugins/expansion/';

 private $templateArr = [
    "text" => ["price", "short"],
     "textarea" => ["goods_content"]
 ];



}