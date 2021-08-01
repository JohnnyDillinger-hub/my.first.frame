<?php


namespace core\base\settings;


use core\base\controller\Singleton;

trait BaseSettings
{

    use Singleton{
        instance as singletonInstance;
    }

    private $baseSettings;

    static public function get($property) // функция для возвращения свойств класса
    {
        return self::instance()->$property; // вызывает свойства объекта
    }

    protected function setProperty($properties)
    {
        if($properties)
        {
            foreach ($properties as $name => $value)
            {
                $this->$name = $value; // сохраняем полученные свойства в объекте данного класса
            }
        }
    }

    static private function instance()
    {
        if(self::$_instance instanceof self) /*instanceof - метод проверки вложенности объекта указанного справа
                                               в объект указанном слева
                                               Пример: *объект проверки* instanceof *вложенный объект* */
        {
            return self::$_instance;
        }

        self::singletonInstance()->baseSettings = Settings::instance(); /* сохраняем в базовых настройках свойства глобального
                                                                  класса настроек "Settings"*/
        $baseProperties = self::$_instance->baseSettings->clueProperties(get_class()); /*вызываем у класса настроек метод
                                                                              для склеивания свойств классов*/
        /* self - конструкция для обращения к классу, внутри которого была объявлена данная конструкция*/

        self::$_instance->setProperty($baseProperties); // передаем склеинные свойства в объект данного класса

        return self::$_instance;
    }

}