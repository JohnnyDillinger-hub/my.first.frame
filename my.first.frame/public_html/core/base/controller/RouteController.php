<?php


namespace core\base\controller;

use core\base\exceptions\RouteException;
use core\base\settings\Settings;


class RouteController extends BaseController
{
    use Singleton;

    protected $routes; // маршруты


    private function __construct()
    {
        // $settings = Settings::instance();
        // $shopSettings = ShopSettings::instance(); //здесь так же вызовутся свойства статического объекта класса "Sett"

        $adress_str = $_SERVER["REQUEST_URI"]; // получаем значение из адресной строки браузера

        if($_SERVER['QUERY_STRING']){
            // обрезаем GET-запросы
            $adress_str = substr($adress_str, 0, strpos($adress_str, $_SERVER['QUERY_STRING']) - 1);
        }

        /*strrpos()  - функция возвращает позицию последнего вхождения подстроки в строке
          1 аргумент - сама строка в которой будет производится поиск
          2 аргумент - искомый символ
          Возвращает позицию, в которой находится искомая строка, относительно начала строки (отсчет ведётся с 0)*/

        // PHP_SELF - имя скрипта, который выполяет код
        /*substr()   - возвращает подстроку
          1 аргумент - строка из которой будет вытаскиваться подстрока
          2 аргумент - порядковый номер символа с которого начать
          3 аргумент - длинна подстроки*/
        $path = substr($_SERVER["PHP_SELF"], 0, strpos($_SERVER["PHP_SELF"], "index.php"));

        if($path === PATH) /* если путь полученный из PHP_SELF, методом ,который прописан выше, равен константе
                              PATH указанной в настройках сайта*/
        {
            if(strrpos($adress_str, "/") === (strlen($adress_str) - 1) &&
                strrpos($adress_str, "/") !== (strlen(PATH) - 1)) /* если порядковый номер символа "/" равен порядковому номеру
                                                     последнего символа в строке И не является единственным
                                                     символом в строке*/
            {
                /* rtrim()    - функция для обрезания пробелов в конце строки
                   1 аргумент - сама строка в которой будет происходить обрезка
                   2 аргумент - (необязательный) символ, который так же нужно вырезать из данной строки */
                $this->redirect(rtrim($adress_str, "/"), 301 /*код-ответ сервера*/);
            }

            // сохраняем в маршрутах главного координатора котроллеров базовые маршруты
            $this->routes = Settings::get("routes");

            if(!$this->routes) // если в свойстве routes нет маршрутов, то...
            {
                throw new RouteException("Отсутствуют маршруты в базовых маршрутах", 1);
            }

            //$check = strpos($adress_str, $this->routes["admin"]["alias"]);

            /* explode - возвращает массив строк, полученных разбиением строки string с использованием delimiter
                  в качестве разделителя.
                  1 аргумент - символ-делитель
                  2 аргумент - порядковый номер символа с которого надо начать формирование массива*/
            $url = explode("/", substr($adress_str, strlen(PATH)));

            // проверяем первый элемент массива на соответстве алиасу административной маршрутизации
            if($url[0] && $url[0] === $this->routes["admin"]["alias"])
            {

                // удаляем из массива первый элемент указывающий на работу с административной панелью
                array_shift($url);

                /*
                // сохраняем URL, что идет после *домен*\admin
                $url = explode("/", substr($adress_str,
                       strlen(PATH . $this->routes["admin"]["alias"]) + 1));
                */

                // проверка на работу с плагином
                /*is_dir()   - определяет, является ли имя файла директорией.
                  1 аргумент - путь к искомому файлу/директории (конвертируется в строковое значние)*/
                // если url[0] вообще имеется И если переданный адрес в is_dir вернет true
                if($url[0] && is_dir($_SERVER["DOCUMENT_ROOT"] . PATH . $this->routes["plugins"]["path"]
                    . $url[0]))
                {

                    // array_shift() - вытаскивает первый элемент массива и производит перетасовку массива
                    // сохраняем название самого плагина и удаляем его из массива
                    $plugin = array_shift($url);

                    // сохраняем в отдельную переменную название класса настроек плагина
                    $pluginSettings = $this->routes["settings"]["path"] . ucfirst($plugin . "Settings");

                    // проверка на наличие файла настроек плагина
                    if(file_exists($_SERVER["DOCUMENT_ROOT"]) . PATH . $pluginSettings . "php")
                    {
                        // склеиваем свойства плагина и базовые свойства, затем сохраняем их
                        $pluginSettings = str_replace("/", '\\', $pluginSettings);
                        $this->routes = $pluginSettings::get("routes"); // получаем свойства плагина
                    }

                    $dir = $this->routes["plugins"]["dir"] ? '/' . $this->routes["plugins"]["dir"] . '/' : '/';
                    // автоматически защищаем от синтаксически неверного маршрута для плагина
                    $dir = str_replace("//", "/", $dir);

                    // путь к контреллеру плагина
                    $this->controller = $this->routes["plugins"]["path"] . $plugin . $dir;

                    $hrUrl = $this->routes["plugins"]["hrUrl"];

                    $route = "admin";

                }else{ // если не плагин, то работаем с административной панелью
                    $this->controller = $this->routes["admin"]["path"]; // сохраняем путь к административному
                                                                        // контроллеру
                    $hrUrl = $this->routes["admin"]["hrUrl"];

                    $route = "admin";
                }

            }else{ //работа с пользовательской частью

                $hrUrl = $this->routes["user"]["hrUrl"];

                $this->controller = $this->routes["user"]["path"]; // сохраняем путь к контроллеру для пользователей

                $admOrUser = "user"; // для кого создается маршрут
            }

            // создаем маршрут
            $this->createRoute($admOrUser, $url);

            if($url[1])
            {
                $count = count($url); // сохраняем количество переменных в массиве
                $key = "";

                if(!$hrUrl)
                {
                    $i = 1;
                }else{
                    // сохраняем первый элемент массива
                    $this->parameters["alias"] = $url[1];
                    $i = 2;
                }

                for(; $i < $count; $i++)
                {
                    if(!$key)
                    {
                        // если пеменная key пустая, то записываем в неё активный элемент массива
                        $key = $url[$i];

                        // создаем пару ключ/значение в массиве parameters, значение подставляем в else
                        $this->parameters[$key] = "";
                    }else{
                        // когда ключ уже не пуст, то ставим ему значение
                        $this->parameters[$key] = $url[$i];
                        $key = "";
                    }
                }
            }

        }else{
            throw new RouteException("Не корректная директория сайта", 1);
        }
    }


    // метод для создания маршрутов
    private function createRoute($admOrUsr, $arrUrl)
    {
        $route = [];

        if(!empty($arrUrl[0])) // если нулевой объект массива не пуст, то это и есть наш контроллер
        {
            if($this->routes[$admOrUsr]["routes"][$arrUrl[0]]) // проверка на наличие алиасов маршрутов
            {
                /*если есть алиасы контроллеров, то обрабатываем их и помещаем в переменную route*/
                $route = explode("/", $this->routes[$admOrUsr]["routes"][$arrUrl[0]]);

                /*
                 * ucfirst()  - функция для перевода первого символа, переданной строки, в верхний регистр
                 * 1 аргумент - строка, в кторой дано сделать изменение*/
                $this-> controller .= ucfirst($route[0] . "Controller");
            }else{
                $this->controller .= ucfirst($arrUrl[0] . "Controller");
            }
        }else{ // если нет нулевого элемента, то есть нет контроллера, то подключаем настройки defaultTemplates
            $this->controller .= $this->routes["defaultRoute"]["controller"]; // подключаем контроллер по дефолту
        }

        /*Если в первой ячейке массива route есть объект, то передаем его в inputMethod, если нет - передаем
          метод по дефолту*/
        $this->inputMethod = $route[1] ? $route[1] : $this->routes["defaultRoute"]["inputMethod"];
        $this->outputMethod = $route[2] ? $route[2] : $this->routes["defaultRoute"]["outputMethod"];

        return;
    }
}