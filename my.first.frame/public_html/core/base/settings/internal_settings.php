<?php

defined("VG_ACCESS") or die("Access denied"); /* функция проверки наличия константы определённой через функция
                                                     define()*/

const MS_MODE = false; // если true, то мы будем разрешать работу с браузером microsoft

const TEMPLATE = "templates/defaultTemplates/";    /*путь к шаблонам пользовательской части сайта. Позволяет быстро изменять
                                            визуальные шаблоны сайта*/
const ADMIN_TEMPLATE = "core/admin/view/"; /*путь к шаблонам административной части сайта. Позволяет быстро изменять
                                            визуальные шаблоны админ. части сайта*/
const UPLOAD_DIR = "userfiles/";

const COOKIE_VERSION = "0.0.1"; // хранение версии куки-файлов
const CRYPT_KEY = 'H+MbQeThWmYq3t6w
                   ?D(G+KbPeShVmYp3
                   x!A%D*G-KaPdSgVk
                   4t7w!z%C*F-JaNdR
                   mYq3t6w9z$C&F)J@
                   ShVkYp3s6v9y$B&E
                   aPdSgUkXp2s5v8y/
                   F-JaNdRgUjXn2r5u';           // ключ шифрования данных
const COOLIE_TIME = 60;         // время бездействия для администратора
const BLOCK_TIME = 3;           // время на которое будет блокироваться пользователь при попытке забрутить пароль

// переменные для постраничной навигации
const QTY = 8;        // константа для хранения количества одновременно отображаемых товаров на странице сайта
const QTY_LINKS = 3;  // константа для хранения количества одновременно отображаемых ссылок для постраничной навигации

// константный массив для хранения путей к CSS-стилям и JS-скриптам административной части сайта
const ADMIN_CSS_JS = [
    "styles"  => ["css/main.css"],
    "scripts" => ['js/frameworkfunction.js', 'js/script_test2.js']
];

// константный массив для хранения путей к CSS-стилям и JS-скриптам пользователськой части сайта
const USER_CSS_JS = [
    "styles"  => [],
    "scripts" => []
];

use core\base\exceptions\RouteException; // импорт пространства имён

function autoloadMainClasses($class_name) // функция автозагрузки классов
{
    $class_name = str_replace('\\', '/', $class_name);
    #include $class_name.'.php';

    // include_once - инструкция однократного подключения файла (отличает от include тем, что проверяет повторы подключения)
    if(!@/*собачка отключает вывод инф. об ошибке от include_once*/include_once $class_name . ".php")
    {
        throw new RouteException("Не верное имя файла для подключения - " . $class_name);
    }
 }
spl_autoload_register('autoloadMainClasses');