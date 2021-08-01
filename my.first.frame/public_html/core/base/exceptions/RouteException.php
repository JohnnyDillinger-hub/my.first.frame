<?php


namespace core\base\exceptions;


use core\base\controller\BaseMethods;

class RouteException extends \Exception // пример наследования
{

    protected $messages; // свойство для хранения всех сообщений полученных из файла messages.php

    use BaseMethods;

    public function __construct($message = "", $code = 0)
    {
        // через parent:: вызывается метод родительского класса
        parent::__construct($message, $code);

        // подключаем файл для хранятся все сообщения об ошибках
        $this->messages = include "messages.php";

        // если сообщение об ошибке существует, то сохраняем его, если нет - ищем подобную ошибку по коду
        $error = $this->getMessage() ? $this->getMessage() : $this->messages[$this->getCode()];

        $error .= "\r\n" . "file" . $this->getFile() . "\r\n" . "In line " . $this->getLine() . "\r\n";

        // если ошибка с полученным кодом существует в стеке сообщений
        // if($this->messages[$this->getCode()]) $this->message = $this->messages[$this->getCode()];

        // записываем сообщение об ошибке в лог
        $this->writeLog($error);
    }
}