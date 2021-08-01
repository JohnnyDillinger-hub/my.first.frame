<?php


namespace core\base\controller;

use core\base\exceptions\RouteException;
use core\base\settings\Settings;

abstract class BaseController
{
    use BaseMethods;

    protected $header;
    protected $content;
    protected $footer;

    protected $page;            // переменная в которой будет хранится вся страница сайта
    protected $error;           // переменная для хранения ошибок вызываемых во время работы и которые нужно залогировать

    protected $template;
    protected $styles;
    protected $scripts;

    protected $controller;      // свойство для хранения контроллера
    protected $inputMethod;     // свойство для хранения метода сбора данных из БД
    protected $outputMethod;    // свойство для хранения метода подключения видов
    protected $parameters;      // свойство для хранения параметров
    protected $routesSettings;  // свойство для хранения базовых настроек

    protected $userId;
    protected $data;            // данные пришедшие из БД для отображения их в шаблоне
    protected $ajaxData;

    // public - так как в index.php метод вызывается извне
    public function route()
    {
        // сохраняем свойства
        // saving properties
        $this->routesSettings = Settings::get('routes');

        $controller = str_replace("/", "\\", $this->controller);

        try {

            /* ReflectionMethod() - сообщает о методах класса переданного в параметрах
            /* ReflectionMethod() - informs about methods of class passed in parameters
               1 аргумент        - класс который будет просматриваться
               2 аргумент        - имя метода
           */
            $object = new \ReflectionMethod($controller, "request");

            $args = [
                "parameters"   => $this->parameters,
                "inputMethod"  => $this->inputMethod,
                "outputMethod" => $this->outputMethod
            ];


            /* вызывает метод (указан вторым аргументом в ReflectionMethod()) у объекта переданного
               первым агрументом в invoke()
               1 аргумент - объект у которого вызвывается метод
               2 аргумент - параметры передаваемые в вызываемый метод*/
            $object->invoke(new $controller, $args);

        }catch (\ReflectionException $e) {
            throw new RouteException($e->getMessage());
        }
    }

    public function request($args)
    {
        $this->parameters = $args["parameters"];

        $inputData = $args["inputMethod"];
        $outputData = $args["outputMethod"];

        $data = $this->$inputData();

        // если в, перданном первым аргументом, классе имеется искомый метод, то отрисовываем страницу
        if(method_exists($this, $outputData))
        {
            $this->page = $this->outputData($data);
        }elseif ($data){ // если искомого метода не существует, но в переменной $data что-то лежит, то передаем
                         // её в свойство $page
            $this->page = $data;
        }

        // если есть ошибка, то сохраняем её в логах
        if($this->error)
        {
            $this->writeLog($this->error); // передаем в метод информацию об ошибке
        }

        $this->getPage();
    }

    // метод для отрисовки страницы в браузере
    protected function render($path = '', $parameters = [])
    {
        /*extract() - функция для преобразования массива вида [ключ => значение] в переменные внутри функции
        Пример: ['name'] => ['Masha'] ====> $name = 'Masha'*/
        @extract($parameters);

        if(!$path)
        {
            $class = new \ReflectionClass($this);

            // сохраняем пространство имён класса переданного выше
            $space = str_replace('\\', '/', $class->getNamespaceName() . '/');

            // сохраняем путь к шаблонам в зависимости от того, кто обращается к сайту
            if($space === $this->routesSettings["user"]["path"])
            {
                $template = TEMPLATE;
            }else{
                $template = ADMIN_TEMPLATE;
            }

            // сохраняем путь к шаблону
            $path = $template . explode("controller", strtolower($class->getShortName()))[0];
        }

        // создаем буффер обмена
        ob_start();

        // проверка на подключение файла шаблона
        if(!@include_once $path . ".php")
        {
            throw new RouteException("Отсутствует шаблон - " . $path);
        }

        // вернет все содержимое буффера обмена и отчистит его
        return ob_get_clean();
    }

    // метод для окончательного показа страницы в браузере
    protected function getPage()
    {
        // если переданное свойство $this->page обычный массив, то выводим на экран все, что в нем хранится
        if(is_array($this->page))
        {
            foreach ($this->page as $block) echo $block;
        }else{
            echo $this->page;
        }
    }

    protected function init($admin = false)
    {
        // проверка на работу с пользовательской или административной маршрутизацией
        if(!$admin)
        {
            // проверка на существование пути(ей) в ячейке "styles"
            if(USER_CSS_JS["styles"])
            {
                // сохраняем каждый путь к стилям в массиве $styles
                foreach (USER_CSS_JS["styles"] as $itemSty)
                {
                    $this->styles[] = PATH . TEMPLATE . '/' . trim($itemSty, "/");
                }
            }

            // проверка на существование пути(ей) в ячейке "scripts"
            if(USER_CSS_JS["scripts"])
            {
                // сохраняем каждый путь к стилям в массиве $scripts
                foreach (USER_CSS_JS["scripts"] as $itemScr)
                {
                    $this->scripts[] = PATH . TEMPLATE . '/' . trim($itemScr, "/");
                }
            }
        }else{
            // проверка на существование пути(ей) в ячейке "styles" для админа
            if(ADMIN_CSS_JS["styles"])
            {
                // сохраняем каждый путь к стилям в массиве $styles
                foreach (ADMIN_CSS_JS["styles"] as $itemSty)
                {
                    $this->styles[] = PATH . ADMIN_TEMPLATE . '/' . trim($itemSty, "/");
                }
            }

            // проверка на существование пути(ей) в ячейке "scripts" для админа
            if(ADMIN_CSS_JS["scripts"])
            {
                // сохраняем каждый путь к стилям в массиве $scripts
                foreach (ADMIN_CSS_JS["scripts"] as $itemScr) {
                    $this->scripts[] = PATH . ADMIN_TEMPLATE . '/' . trim($itemScr, "/");
                }
            }
        }
    }
}