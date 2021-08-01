<?php

namespace core\base\controller;

use core\base\settings\Settings;

class BaseAjax extends BaseController
{
    // определяет какой контроллер подключать
    public function route()
    {
        $route = Settings::get('routes');

        $controller = $route['user']['path'] . 'AjaxController';

        // если запрос пришел POST'ом, то сохраняем данные из POST запроса, если нет, то сохраняем данные из GET запроса
        $postOrGet = $this->isPost() ? $_POST : $_GET;

        $httpReferer = str_replace('/', '\/',
            $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['SERVER_NAME'] . PATH . $route['admin']['alias']);

        // если запрос пришел с флагом работы из административной части сайта, то меняем путь к контроллеру
        if(isset($postOrGet['ADMIN_MODE']) || preg_match('/^' . $httpReferer . '(\/?|$)/', $_SERVER['HTTP_REFERER']))
        {
            unset($postOrGet['ADMIN_MODE']);

            $controller = $route['admin']['path'] . 'AjaxController';
        }

        $controller = str_replace('/', '\\', $controller);

        // инициализируем ajax контроллер
        $ajax = new $controller;

        // сохраняем то, что пришло в запросе
        $ajax->ajaxData = $postOrGet;

        $res = ($ajax->ajax());

        if((is_array($res)) || is_object($res)) $res = json_encode($res);
            elseif(is_int($res)) $res = (float)$res;

        return $res;
    }

}