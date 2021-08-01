<?php


namespace core\base\controller;


trait Singleton
{

    static private $_instance;

    private function __construct()
    {
    }

    private function __clone()
    {
    }

    static public function instance()
    {
        if(self::$_instance instanceof self) /*instanceof - метод проверки вложенности объекта указанного справа,
                                               в объект указанном слева
                                               Пример: *объект проверки* instanceof *вложенный объект* */
        {
            return self::$_instance; // возвращает объект данного класса
        }

        self::$_instance = new self;

        // проверяем наличие метода "connectDB" в подключаемом классе
        // checking presence method "connectDB" in pluggable class
        if(method_exists(self::$_instance, 'connectDB')) self::$_instance->connectDB();

        /* self - construct fo referring to class, within which this construct was declared
        /* self - конструкция для обращения к классу, внутри которого была объявлена данная конструкция*/
        return self::$_instance;
    }

}