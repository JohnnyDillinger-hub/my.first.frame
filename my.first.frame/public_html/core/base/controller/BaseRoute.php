<?php

namespace core\base\controller;

class BaseRoute
{

    use Singleton, BaseMethods;

    //проверка на работу с синхронной или асинхронной маршрутизацией
    public static function routeDirection()
    {

        if(self::instance()->isAjax())
        {
            exit((new BaseAjax())->route());
        }

        RouteController::instance()->route();
    }
}