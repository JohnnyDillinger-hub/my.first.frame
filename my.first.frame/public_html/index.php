<?php

define("VG_ACCESS", true); /*глобальная константа безопасности. Нужна для невозможности, со стороны пользователя,
                             получить доступ к системным(конфигуративным) файлам сайта */

header("Content-Type:text/html;charset-utf-8"); /* фунция header() отправляет данные непосредственно
                                                          браузеру с информацией указанной в аргументе функции.
                                                          "Content-Type:text/html;charset-utf-8" - указываем браузеру
                                                          пользователя информацию о том, в какой кодировке будем
                                                          отправлять данные.
                                                          Заголовки(header()) нужно отправляет перед выводом какой
                                                          либо информации пользователю*/

session_start(); /*запускает сессию для каждого отдельного пользователя пo ip, генерирую файлы (куки), в которых
                   хранится информация о каждой отдельной сессии. Сессия завершается только после закрытия браузера
                   а не самой вкладки в браузере*/

require_once 'config.php'; // функция для подключения указанного в кавычках файла
require_once 'core/base/settings/internal_settings.php'; // файл для хранения глобальных настроек отдельного проекта

use core\base\exceptions\RouteException; // импорт пространства имён
use core\base\controller\BaseRoute;
use core\base\exceptions\DbException;

try
{
    BaseRoute::routeDirection(); /* :: - вызов статичного метода определённого класса*/
}
catch (RouteException $ex)
{
    exit($ex->getMessage());
}

catch (DbException $dbEx)
{
    exit($dbEx->getMessage());
}
catch (Exception $e){

    exit($e->getMessage());

}