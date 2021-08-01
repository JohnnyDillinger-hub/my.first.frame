<?php


namespace core\base\settings;


use core\base\controller\Singleton;

class Settings
{
    use Singleton;

    private $routes = [ // свойство "маршруты"
        "admin" => [ // ячейка массива для маршрутизации административного пользователя
            "alias"   => "admin", // имя указываемое при маршрутизации по поисковой строке браузера
            "path"    => "core/admin/controller/", // путь к контроллеру админа
            "hrUrl"   => false, // определяет читабельность для человека адерса url(false - для админов)
        ],
        "settings"    => [ // ячейка маршрутизации настроек
            "path"    => "core/base/settings/"
        ],
        "plugins"     => [ // ячейка маршрутизации плагинов сайта
            "path"    => "core/plugins/",
            "hrUrl"   => false,
            "dir"     => false
        ],
        "user" => [
            "path"    => "core/user/controller/",
            "hrUrl"   => true, // определяет читабельность для человека адерса url(true - для пользвателей)
            "routes"  => [
                "site" => "index/hello/"
            ]
        ],
        "defaultRoute"     => [ // ячейка для дефолтной маршрутизации
            "controller"   => "indexController", // определяем контроллер по умолчанию
            "inputMethod"  => "inputData", // метод вызова по умолчанию
            "outputMethod" => "outputData" // метод передачи запрашиваемых данных в пользовательскую ячейку массива
        ]
    ];

    // путь к рассширеням
    private $expansion = 'core/admin/expansion/';

    // свойство для транслитерации названия родительских полей шаблонов на сайте
    private $projectTables = [
        'articles'  => ['name' => 'Статьи'],
        'pages'     => ['name' => 'Разделы'],
      'goods'       => ['name' => 'Товары', 'img' => 'pages.png'],
      'filters'     => ['name' => 'Фильтры']
    ];

    private $messages = 'core/base/messages/';

    private $defaultTable = 'goods';

    private $formTemplates = PATH . 'core/admin/view/include/form_templates/';

    // массив-связка шаблоны с талицами из БД
    private $templateArr = [
        "text"         => ['name'],
        "textarea"     => ['content', 'keywords'],
        'radio'        => ['visible'],
        'checkboxlist' => ['filters'],
        'select'       => ['parent_id', 'menu_position'],
        'img'          => ['img'],
        'gallery_img'  => ['gallery_img', 'new_gallery_img']
    ];

    private $fileTemplates = ['img', 'gallery_img'];

    // в первой ячейке 'name' хранится перевод, а во второй - комментарий
    private $translate = [
        'name' => ['Название', 'Не более 100 символов'],
        'keywords' => ['Ключевые слова', 'Не более 70 символов'],
    ];

    // массив обозначения корневой таблицы для связи между БД
    private $rootItems = [
      'name'   => 'Корневая',
      'tables' => ['articles']
    ];

    private $radioTap = [
      'visible' => ['Нет', 'Да', 'default' => 'Да']
    ];

    private $manyToMany = [
        'goods_filters' => ['goods', 'filters'/*'type' => 'parent' || 'child'*/]
    ];


    // массив распределение шаблонов по блокам визуализации
    private $blockNeedle = [
        'vg-rows'    => [],
        'vg-img'     => ['img'],
        'vg-content' => ['content']
    ];



    // массив полей которые мы будем валидировать
    private $validation = [
      'name'      => ['empty' => true, 'trim' => true],
      'price'     => ['int' => true],
      'login'     => ['empty' => true, 'trim' => true],
       'password' => ['crypt' => true, 'empty' => true],
       'keywords' => ['count' => 70, 'trim' => true],
        'description' => ['count' => 160, 'trim' => true],
    ];

    static public function get($property) // функция для возвращения свойств класса
    {
        return self::instance()->$property; // вызывает свойства определнные в массиве "routes"
    }


    public function clueProperties($class)
    {
        $baseProperties = [];
        /*
         * $this - объект перебора
         * $name - ключ
         * $item - значение
         * */
        foreach ($this as $name => $item)
        {
            $property = $class::get($name/*ключ свойсства*/); // получаем свойства объекта переданого в параметрах функции
            // $baseProperties[$name] = $property; // сохраняем свойства перебираемого массива

            if(is_array($property)/*Проверка: массив ли*/ && is_array($item)) /*если при переборе оба свойства объекта
                                                                                являются массивами - то склеиваем их*/
            {
                /*$baseProperties[$name] = array_merge_recursive($this->$name, $property); - склеиваем свойства объетов
                                                                                           при промощи рекурсивной
                                                                                           функции*/
                $baseProperties[$name] = $this->arrayMergeRecursive($this->$name, $property);
                continue;
            }

            if(!$property) /* если в property пришел не массив, то записываем то, что находится в свойствах основного
                              класса настроек */
            {
                $baseProperties[$name] = $this->$name;
            }
        }

        return $baseProperties;
    }

    public function arrayMergeRecursive() // кастомный метод склеивания свойств/массивов объектов
    {
        $arrays = func_get_args(); // - функция "вытаскивает" из памяти все аргументы переданные в параметры метода

        $base = array_shift($arrays); /* - функция "вытягивает" нулевой элемент массива и мы сохраняем
                                                его в переменной "$base", но данный элемент уже отсутствует
                                                в массиве "$arrays"*/
        foreach ($arrays as $arrayIndex) // перебор по всем вложенным элементам массива
        {
            foreach ($arrayIndex as $key => $value)
            {
                if(is_array($value) && is_array($base[$key])) /*проверка на многомерность массива, как раз то,
                                                                что нам и нужно*/
                {
                    $base[$key] = $this->arrayMergeRecursive($base[$key], $value);
                }
                else
                {
                    if(is_int($key)/*функция для проверки целочисленности переданного значения*/)
                    {
                        /*in_array() - проверяет наличие определённого значения в переданном массиве
                          1 агрумент - значение которе мы ищем
                          2 аргумент - массив в котором осуществляем поиск*/
                        if(!in_array($value, $base)) // если в массиве нет искомого элемента, то добавляем его
                        {
                            /*array_push() - добавляет указаннный элемент в массив
                              1 аргумент - массив в который добавляем
                              2 аргумент - сам элемент, который добавляем*/
                            array_push($base, $value);
                            continue;
                        }
                    }
                    $base[$key] = $value; // если массив имеет ключ в строковой ориентации, то просто его
                    // перезаписываем
                }
            }
        }

        return $base;
    }
}