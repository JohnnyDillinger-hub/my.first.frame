<?php


namespace core\admin\controller;


use Cassandra\Set;
use core\admin\models\Model;
use core\base\controller\BaseController;
use core\base\exceptions\RouteException;
use core\base\settings\Settings;
use libraries\FileEdit;

// класс для сборки шаблонов административной части сайта
abstract class BaseAdmin extends BaseController
{

    protected $model;

    protected $table;     // хранит название таблицы БД
    protected $columns;   // хранит колонки полученные из БД
    protected $foreignData = [];

    protected $adminPath; // хранит административный путь
    protected $messages;  // хранит путь к файлу в котором хранятся прототипы всех сообщений
    protected $settings;

    protected $menu;
    protected $title;

    protected $fileArray;
    protected $alias;

    protected $translate; // свойство для хранения языковой локализации
    protected $blocks = [];

    protected $templateArr;
    protected $formTemplates;
    protected $noDelete;

    protected function inputData()
    {

        // checking type and browser version
        if(!MS_MODE)
        {

            if(preg_match('/msie|trident.+?rv\s*:/i', $_SERVER['HTTP_USER_AGENT']))
            {

                exit('You are using outdated browser version. Please, update to the latest version');

            }

        }

        $this->init(true);

        $this->title = 'VG engine';

        // создаем экземплер класса для подключения к модели
        if (!$this->model) $this->model         = Model::instance();
        if (!$this->menu) $this->menu           = Settings::get('projectTables');
        // формируем административный путь
        if (!$this->adminPath) $this->adminPath = PATH . Settings::get('routes')['admin']['alias'] . '/';

        // получаем свойства шаблонов
        if(!$this->templateArr) $this->templateArr = Settings::get('templateArr');
        if(!$this->formTemplates) $this->formTemplates = Settings::get('formTemplates');

        if(!$this->messages) $this->messages = include $_SERVER['DOCUMENT_ROOT'] . PATH .
                            Settings::get('messages') . 'informationMessages.php';

        $this->sendNoCacheHeaders();

    }

    protected function outputData()
    {

        if(!$this->content)
        {
            $args = func_get_arg(0);
            $vars = $args ? $args : [];

            //if(!$this->template) $this->template = ADMIN_TEMPLATE . '/show';

            $this->content = $this->render($this->template, $vars);
        }

        $this->header = $this->render(ADMIN_TEMPLATE . 'include/header');
        $this->footer = $this->render(ADMIN_TEMPLATE . 'include/footer');

        return $this->render(ADMIN_TEMPLATE . 'layout/default');

    }

    protected function sendNoCacheHeaders()
    {
        // отправляет информацию о последней модификации контента
        header("Last-Modified: " . gmdate("D, d m Y H:i:s") . " GMT");

        // обязывает браузер каждый раз отправлять запрос к нашему серверу для валидации кэша
        header("Cache-Control: no-cache, must-revalidate");

        // обязывает браузер загружать информацию с сервера, а не показывать кэшированную
        header("Cache-Control: max-age=0");

        /*хедер для internet explorer, обязывает его постоянно обновлять данные после загрузки
          и после демострации страницы пользователю*/
        header("Cache-Control: post-check=0,pre-check=0");
    }

    protected function execBase()
    {
        self::inputData();
    }

    // метод получения данных из таблицы
    protected function createTableData($settings = false)
    {

        if(!$this->table)
        {
            if($this->parameters) $this->table = array_keys($this->parameters)[0];
                else
                {
                    if(!$settings) $settings = Settings::instance();
                    $this->table = Settings::get('defaultTable');
                }
        }

        $this->columns = $this->model->showColumns($this->table);

        if(!$this->columns) new RouteException("Не найдены поля в таблице -" . $this->table,
            2);

    }



    protected function expansion($args = [], $settings = false)
    {

        $fileName = explode("_", $this->table);
        $className = '';

        foreach ($fileName as $item) $className .= ucfirst($item);


        if(!$settings)
        {
            // если не пришел объект
            $path = Settings::get('expansion');
        }elseif (is_object($settings))
        {
            // если пришел объект
            $path = $settings::get('expansion');
        }else{
            // если вместо объекта пришел просто путь
            $path = $settings;
        }

        $class = $path . $className . 'Expansion';

        if(is_readable($_SERVER['DOCUMENT_ROOT'] . PATH . $class . '.php'))
        {
            $class = str_replace('/', '\\', $class);

            $exp = $class::instance();

            foreach ($this as $key => $value)
            {
                $exp->$key = $this->$key;
            }

            return $exp->expansion($args);
        }else{

            $file = $_SERVER['DOCUMENT_ROOT'] . PATH . $path . $this->table . '.php';

            // создаем переменные из массива
            extract($args);

            // если файл читается, то подключаем его
            if(is_readable($file)) return include $file;

        }

        return false;
    }

    protected function createOutputData($settings = false)
    {

        if(!$settings) $settings = Settings::instance();

        $blocks = Settings::get('blockNeedle');
        $this->translate = Settings::get('translate');

        if(!$blocks || !is_array($blocks))
        {
            foreach ($this->columns as $name => $value)
            {
                if($name = 'id_row') continue;

                // пустые [] автоматически ставят значение 0
                if(!$this->translate[$name]) $this->translate[$name][] = $name;
                $this->blocks[0][] = $name;
            }

            return;
        }

        $defaultBlock = array_keys($blocks)[0];

        foreach ($this->columns as $name => $value)
        {
            if($name === 'id_row') continue;

            $insert = false;

            foreach ($blocks as $block => $item)
            {
                if(!array_key_exists($block, $this->blocks)) $this->blocks[$block] = [];

                if(in_array($name, $item))
                {
                     $this->blocks[$block][] = $name;
                     $insert = true;
                     break;
                }
            }

            if(!$insert) $this->blocks[$defaultBlock][] = $name;
            if(!$this->translate[$name]) $this->translate[$name][] = $name;
        }

        return;
    }

    protected function createRadioTap($settings = false)
    {
        if(!$settings) $settings = Settings::instance();

        $radioTap = $settings::get('radioTap');

        if($radioTap)
        {
            foreach ($this->columns as $name => $value)
            {
                if($radioTap[$name])
                {
                    $this->foreignData[$name] = $radioTap[$name];
                }
            }
        }
    }

    protected function checkPost($settings = false)
    {

        if(!$settings) $settings = Settings::instance();

        if($this->isPost())
        {
            $this->clearPostFields($settings);

            $this->table = $this->clearStr($_POST['table']);

            unset($_POST['table']);

            if($this->table)
            {
                $this->createTableData($settings);

                $this->editData();
            }
        }

    }

    protected function clearPostFields($settings, &$arr = [])
    {

        if(!$arr) $arr = &$_POST;
        if(!$settings) $settings = Settings::instance();

        $id = $_POST[$this->columns['id_row']] ?: false;

        $validate = Settings::get('validation');

        if(!$this->translate)$this->translate = Settings::get('translate');

        foreach ($arr as $key => $item) {
            if (is_array($item)) {
                
                $this->clearPostFields($settings, $item);

            } else {

                if (is_numeric($item)) {

                    $arr[$key] = $this->clearNum($item);
                }

                if ($validate) {

                    if ($validate[$key]) {

                        if ($this->translate[$key]) {

                            $answer = $this->translate[$key][0];

                        } else {
                            $answer = $key;
                        }

                        if ($validate[$key]['crypt']) {

                            if ($id) {

                                if (empty($item)) {

                                    unset($arr[$key]);
                                    continue;
                                }

                                $arr[$key] = md5($item);
                            }

                        }

                        if ($validate[$key]['empty']) $this->emptyFields($item, $answer, $arr);

                        if ($validate[$key]['trim']) $arr[$key] = trim($item);

                        if ($validate[$key]['int']) $arr[$key] = $this->clearNum($item);

                        if ($validate[$key]['count']) $this->countChar($item, $validate[$key]['count'], $answer, $arr);

                    }
                }
            }
        }

        return true;
    }

    protected function addSessionData($arr = [])
    {
        if(!$arr) $arr = $_POST;

        foreach ($arr as $key => $item)
        {
            $_SESSION['res'][$key] = $item;

            $this->redirect();
        }
    }

    protected function countChar($str, $counter, $answer, $arr)
    {

        if(mb_strlen($str) > $counter){
            $str_res = mb_str_replace('$1', $answer, $this->messages['count']);
            $str_res = mb_str_replace('$2', $counter, $str_res);

            $_SESSION['res']['answer'] = '<div class="error">' . $str_res . ' ' . $answer . '</div>';
            $this->addSessionData($arr);
        }
    }

    protected function emptyFields($str, $answer, $arr = [])
    {

        if(empty($str))
        {
            $_SESSION['res']['answer'] = '<div class="error">' . $this->messages['empty'] . ' ' . $answer . '</div>';
            $this->addSessionData($arr);
        }

    }

    // метод обновления(добавления правок) данных
    protected function editData($returnId = false)
    {

        $id = false;
        $method = 'add';

        if(!empty($_POST['return_id'])) $returnId = true;

        if($_POST[$this->columns['id_row']]) {
            $id = is_numeric($_POST[$this->columns['id_row']]) ?
                $this->clearNum($_POST[$this->columns['id_row']]) :
                $this->clearStr($_POST[$this->columns['id_row']]);

            if ($id) {
                $where = [$this->columns['id_row'] => $id];
                $method = 'edit';
            }
        }

        foreach ($this->columns as $key => $item) {

            if($key === 'id_row') continue;

            if ($item['Type'] === 'date' || $item['Type'] === 'datetime') {
                !$_POST[$key] && $_POST[$key] = 'NOW()';
            }

        }

        $this->createFile();

        if($id && method_exists($this, 'checkFiles')) $this->checkFiles($id);

        $this->createAlias($id);

        $this->updateMenuPosition();

        $except = $this->checkExceptFields();

        $res_id = $this->model->$method($this->table, [
            'files' => $this->fileArray,
            'where' => $where,
            'return_id' => true,
            'except' => $except
        ]);

        if (!$id && $method === 'add') {
            $_POST[$this->columns['id_row']] = $res_id;
            $answerSuccess = $this->messages['addSuccess'];
            $answerFail = $this->messages['addFail'];
        } else {
            $answerSuccess = $this->messages['editSuccess'];
            $answerFail = $this->messages['editFail'];
        }

        $this->checkManyToMany();

        $this->expansion(get_defined_vars());

        $result = $this->checkAlias($_POST[$this->columns['id_row']]);

        if ($res_id)
        {
            $_SESSION['res']['answer'] = '<div class="success">'. $answerSuccess .'</div>';

            if(!$returnId) $this->redirect();

            return $_POST[$this->columns['id_row']];

        }else{
            $_SESSION['res']['answer'] = '<div class="error">'. $answerFail .'</div>';

            if(!$returnId) $this->redirect();
        }

    }

    protected function checkExceptFields($arr = [])
    {

        if(!$arr) $arr = $_POST;

        if($arr){
            $except = [];
            foreach ($arr as $key => $item) {
                if(!$this->columns[$key]) $except[] = $key;
            }
        }

        return $except;

    }

    protected function createFile()
    {

        $fileEdit = new FileEdit();
        $this->fileArray = $fileEdit->addFile();

    }

    protected function updateMenuPosition($id = false)
    {

        if(isset($_POST['menu_position']))
        {

            $where = false;

            if($id && $this->columns['id_row']) $where = [$this->columns['id_row'] => $id];

            if(array_key_exists('parent_id', $_POST))
            {
                $this->model->updateMenuPositionAdmin($this->table, 'menu_position', $where,
                    $_POST['menu_position'], ['where' => 'parent_id']);
            }else{
                $this->model->updateMenuPositionAdmin($this->table, 'menu_position', $where,
                    $_POST['menu_position']);
            }

        }

    }

    protected function createAlias($id = false)
    {

        if($this->columns['alias']){

            if(!$_POST['alias']){

                if($_POST['name']){

                    $alias_str = $this->clearStr($_POST['name']);

                }else{
                    foreach ($_POST as $key => $item) {

                        if(strpos($key, 'name') !== false && $item){

                            $alias_str = $this->clearStr($item);
                            break;

                        }
                    }
                }
            }else{

                //
                $alias_str = $_POST['alias'] = $this->clearStr($_POST['alias']);

            }

            $textModify = new \libraries\TextModify();
            $alias = $textModify->translit($alias_str);

            $alias = 'teachers_111';

            $where['alias'] = $alias;
            $operand = '=';

            if($id){
                $where[$this->columns['id_row']] = $id;
                $operand[] = '<>';
            }

            $res_alias = $this->model->get($this->table, [
               'fields' => ['alias'],
                'where' => $where,
                'operand' => $operand,
                'limit' => '1'
            ])[0];

            if(!$res_alias){

                $_POST['alias'] = $alias;

            }else{

                $this->alias = $alias;
                $_POST['alias'] = '';
            }

            if($_POST['alias'] && $id)
            {
                method_exists($this, 'checkOldAlias') && $this->checkOldAlias();
            }
        }

    }

    protected function checkAlias($id)
    {

        if($id){
            if($this->alias){
                $this->alias .= '-' . $id;

                $this->model->edit($this->table, [
                    'fields' => ['alias' => $this->alias],
                    'where'  => [$this->columns['id_row'] => 'id']
                ]);

                return true;
            }
        }

        return false;

    }

    protected function createOrderData($table)
    {
        $columns = $this->model->showColumns($table);

        if(!$columns)
            throw new RouteException('Отсутствуют поля в таблице ' . $table);

        $name = '';
        $orderName = '';

        if($columns['name'])
        {
            $orderName = $name = 'name';
        }else{
            foreach ($columns as $key => $value)
            {
                if(strpos($key, 'name') !== false)
                {
                    $orderName = $key;
                    $name = $key . ' as name';
                }
            }

            if(!$name) $name = $columns['id_row'] . ' as name';
        }

        $parent_id = '';
        $order = [];

        if($columns['parent_id'])
        {
            $order[] = $parent_id = 'parent_id';
        }

        if($columns['menu_position'])
        {
            $order[] = 'menu_position';
        }else{
            $order[] = $orderName;
        }

        return compact('name', 'parent_id', 'order', 'columns');

    }

    protected function createManyToMany($settings = false)
    {
        if(!$settings) $settings = $this->settings ?: Settings::instance();

        $manyToMany = $settings::get('manyToMany');
        $blocks = $settings::get('blockNeedle');

        if($manyToMany)
        {
            foreach ($manyToMany as $mTable => $m_tables)
            {

                $targetKey = array_search($this->table, $m_tables);

                if($targetKey !== false)
                {
                    $otherKey = $targetKey ? 0 : 1;

                    $checkBoxList = $settings::get('templateArr')['checkboxlist'];

                    if(!$checkBoxList || !in_array($m_tables[$otherKey], $checkBoxList)) continue;

                    if(!$this->translate[$m_tables[$otherKey]])
                    {
                        if($settings::get('projectTables')[$m_tables[$otherKey]])
                            $this->translate[$m_tables[$otherKey]] =
                                [$settings::get('projectTables')[$m_tables[$otherKey]]['name']];
                    }

                    $orderData = $this->createOrderData($m_tables[$otherKey]);

                    $insert = false;

                    // добавляем имя таблицы, чтобы потом подключить нужный шаблон
                    if($blocks)
                    {
                         foreach ($blocks as $key => $item)
                         {
                             if(!in_array($m_tables[$otherKey], $item))
                             {
                                 $this->blocks[$key][] = $m_tables[$otherKey];
                                 $insert = true;
                                 break;
                             }
                         }

                         if(!$insert) $this->blocks[array_keys($this->blocks)[0] = $m_tables[$otherKey]];

                         $foreign = [];

                         if($this->data)
                         {

                             $res = $this->model->get($mTable,[
                                 'fields' => [$m_tables[$otherKey] . '_' . $orderData['columns']['id_row']],
                                 'where'  => [$this->table . '_' . $this->columns['id_row'] => $this->data[$this->columns['id_row']]]
                             ]);

                             if($res)
                             {
                                 foreach ($res as $item)
                                 {
                                     $foreign[] = $item[$m_tables[$otherKey] . '_' . $orderData['columns']['id_row']];
                                 }
                             }

                         }

                         if(isset($m_tables['type'])) {
                             $data = $this->model->get($m_tables[$otherKey], [
                                 'fields' => [
                                     $orderData['columns']['id_row'] . ' as id', $orderData['name'], $orderData['parent_id']
                                 ],
                                 'order' => $orderData['order']
                             ]);

                             if ($data) {

                                 $this->foreignData[$m_tables[$otherKey]][$m_tables[$otherKey]]['name'] = 'Выбрать';

                                 foreach ($data as $item) {

                                     if ($m_tables['type'] === 'parent' && $orderData['parent_id']) {
                                         if ($item[$orderData['parent_id']] === null)
                                             $this->foreignData[$m_tables[$otherKey]][$m_tables[$otherKey]]['sub'][] = $item;

                                     } elseif ($m_tables['type'] === 'child' && $orderData['parent_id']) {

                                         if ($item[$orderData['parent_id']] !== null)
                                             $this->foreignData[$m_tables[$otherKey]][$m_tables[$otherKey]]['sub'][] = $item;
                                     } else {

                                         $this->foreignData[$m_tables[$otherKey]][$m_tables[$otherKey]]['sub'][] = $item;

                                     }

                                     if (in_array($item['id'], $foreign))
                                         $this->data[$m_tables[$otherKey]][$m_tables[$otherKey]][] = $item['id'];

                                 }
                             }
                         }elseif($orderData['parent_id']){

                             $parent = $m_tables[$otherKey];

                             $keys = $this->model->showForeignKeys($m_tables[$otherKey]);

                             if($keys)
                             {
                                 foreach ($keys as $item)
                                 {
                                     if($item['COLUMN_NAME'] === 'parent_id')
                                     {
                                         // $item['REFERENCED_TABLE_NAME'] - имя таблицы с которой произведена связь
                                         $parent = $item['REFERENCED_TABLE_NAME'];

                                         break;
                                     }
                                 }
                             }

                             if($parent === $m_tables[$otherKey])
                             {

                                 $data = $this->model->get($m_tables[$otherKey], [
                                     'fields' => [
                                         $orderData['columns']['id_row'] . ' as id', $orderData['name'], $orderData['parent_id']
                                     ],
                                     'order' => $orderData['order']
                                 ]);

                                 if($data)
                                 {
                                     while(($key = key($data)) !== null)
                                     {
                                        if(!$data[$key]['parent_id'])
                                        {
                                            $this->foreignData[$m_tables[$otherKey]][$data[$key]['id']]['name'] =
                                                $data[$key]['name'];
                                            unset($data[$key]);
                                            // сбрасываем указатель по массиву, Чтобы начать итерацию с нуля
                                            reset($data);
                                            continue;

                                        }else{

                                           if($this->foreignData[$m_tables[$otherKey]][$data[$key][$orderData['parent_id']]])
                                           {

                                               $this->foreignData[$m_tables[$otherKey]][$data[$key][$orderData['parent_id']]]['sub'][$data[$key]['id']] =
                                                   $data[$key];

                                               if(in_array($data[$key]['id'], $foreign))
                                                   $this->data[$m_tables[$otherKey]][$data[$key][$orderData['parent_id']]][] = $data[$key]['id'];

                                               unset($data[$key]);
                                               reset($data);
                                               continue;
                                           }else{

                                               foreach ($this->foreignData[$m_tables[$otherKey]] as $id => $item)
                                               {

                                                   $parent_id = $data[$key][$orderData['parent_id']];

                                                   if(isset($item['sub']) && $item['sub'] && isset($item['sub'][$parent_id]))
                                                   {

                                                       $this->foreignData[$m_tables[$otherKey]][$id]['sub'][$data[$key]['id']] = $data[$key];

                                                       if(in_array($data[$key]['id'], $foreign))
                                                           $this->data[$m_tables[$otherKey]][$id][] = $data[$key]['id'];

                                                       unset ($data[$key]);
                                                       reset($data);

                                                       continue 2;
                                                   }
                                               }
                                           }

                                           // перемещаем указатель цикла
                                           next($data);
                                        }
                                     }
                                 }
                             }else{

                                 $parentOrderData = $this->createOrderData($parent);

                                 $data = $this->model->get($parent, [
                                     'fields' => [$parentOrderData['name']],
                                     'join' => [
                                         $m_tables[$otherKey] => [
                                             'fields' => [$orderData['columns']['id_row'] . ' as id', $orderData['name']],
                                             'on' => [$parentOrderData['columns']['id_row'], $orderData['parent_id']]
                                         ]
                                     ],

                                     'join_structure' => true
                                 ]);

                                 foreach ($data as $key => $item)
                                 {
                                     if(isset($item['join'][$m_tables[$otherKey]]) && $item['join'][$m_tables[$otherKey]])
                                     {

                                         $this->foreignData[$m_tables[$otherKey]][$key]['name'] = $item['name'];
                                         $this->foreignData[$m_tables[$otherKey]][$key]['sub'] = $item['join'][$m_tables[$otherKey]];

                                         foreach ($item['join'][$m_tables[$otherKey]] as $value)
                                         {
                                             if(in_array($value['id'], $foreign))
                                             {
                                                 $this->data[$m_tables[$otherKey]][$key][] = $value['id'];
                                             }
                                         }

                                     }
                                 }

                             }
                         }else{

                             $data = $this->model->get($m_tables[$otherKey], [
                                'fields' => [$orderData['columns']['id_row'] . ' as id', $orderData['name'], $orderData['parent_id']],
                                'order'  => $orderData['order']
                             ]);

                             if($data)
                             {

                                 $this->foreignData[$m_tables[$otherKey]][$m_tables[$otherKey]]['name'] = 'Выбрать';

                                 foreach ($data as $item)
                                 {
                                     $this->foreignData[$m_tables[$otherKey]][$m_tables[$otherKey]]['sub'] = $item;

                                     if(in_array($item['id'], $foreign))
                                         $this->data[$m_tables[$otherKey]][$m_tables[$otherKey]][] = $item['id'];
                                 }

                             }
                         }
                    }
                }
            }
        }
    }

    protected function checkManyToMany($settings = false)
    {
        if(!$settings) $settings = $this->settings?: Settings::instance();

        $manyToMany = $settings::get('manyToMany');

        if($manyToMany)
        {
            foreach ($manyToMany as $mTable => $m_tables)
            {
                $targetKey = array_search($this->table, $m_tables);

                if($targetKey !== false)
                {
                    $otherKey = $targetKey ? 0 : 1;

                    $chekBoxList = $settings::get('templateArr')['checkboxlist'];

                    if(!$chekBoxList || !in_array($m_tables[$otherKey], $chekBoxList)) continue;

                    $columns = $this->model->showColumns($m_tables[$otherKey]);

                    $targetRow = $this->table . '_' . $this->columns['id_row'];

                    $otherRow = $m_tables[$otherKey] . '_' . $columns['id_row'];

                    $this->model->delete($mTable, [
                       'where' => [$targetRow => $_POST[$this->columns['id_row']]]
                    ]);

                    if($_POST[$m_tables[$otherKey]])
                    {
                        $insertArr = [];
                        $i = 0;

                        foreach ($_POST[$m_tables[$otherKey]] as $value)
                        {
                            foreach ($value as $item)
                            {
                                if($item)
                                {
                                    $insertArr[$i][$targetRow] = $_POST[$this->columns['id_row']];
                                    $insertArr[$i][$otherRow]  = $item;

                                    $i++;
                                }
                            }
                        }

                        if($insertArr)
                        {

                            $this->model->add($mTable, [
                                'fields' => $insertArr
                            ]);

                        }
                    }
                }
            }
        }
    }

    protected function createForeignProperty($arr, $rootItems)
    {


        if(in_array($this->table, $rootItems['tables']))
        {
            $this->foreignData[$arr['COLUMN_NAME']][0]['id'] = 'NULL';
            $this->foreignData[$arr['COLUMN_NAME']][0]['name'] = $rootItems['name'];
        }

        // $columns = $this->model->showColumns($arr['REFERENCED_TABLE_NAME']);

        $orderData = $this->createOrderData($arr['REFERENCED_TABLE_NAME']);

        if($this->data)
        {
            if($arr['REFERENCED_TABLE_NAME'] === $this->table)
            {
                $where[$this->columns['id_row']] = $this->data[$this->columns['id_row']];
                $operand[] = '<>';
            }
        }

        $foreign = $this->model->get($arr['REFERENCED_TABLE_NAME'], [
            'fields'  =>  [$arr['REFERENCED_COLUMN_NAME'] . ' as id', $orderData['name'], $orderData['parent_id']],
            'where'   =>  $where,
            'operand' =>  $operand,
            'order'   =>  $orderData['order']
        ]);

        if($foreign)
        {
            if($this->foreignData[$arr['COLUMN_NAME']])
            {
                foreach ($foreign as $value) {
                    $this->foreignData[$arr['COLUMN_NAME']][] = $value;
                }
            }else{
                $this->foreignData[$arr['COLUMN_NAME']] =  $foreign;
            }
        }
    }

    protected function createForeignData($settings = false)
    {
        if(!$settings) $settings = Settings::instance();

        $rootItems = Settings::get('rootItems');

        $keys = $this->model->showForeignKeys($this->table);

        if($keys)
        {
            foreach($keys as $item)
            {

                $this->createForeignProperty($item, $rootItems);

            }
        }elseif ($this->columns['parent_id']){

            $arr['COLUMN_NAME'] = 'parent_id';
            $arr['REFERENCED_COLUMN_NAME'] = $this->columns['id_row'];
            $arr['REFERENCED_TABLE_NAME'] = $this->table;

            $this->createForeignProperty($arr, $rootItems);

        }

        return;
    }

    protected function createMenuPosition($settings = false)
    {

        if($this->columns['menu_position']){

            if($this->columns['parent_id'])
            {
                if(!$settings) $settings = Settings::instance();
                $rootItems = $settings::get('rootItems');

                if($this->columns['parent_id'])
                {
                    if(in_array($this->table, $rootItems['tables']))
                    {
                        $where = 'parent_id IS NULL OR parent_id = 0';
                    }
                }else{

                    $parent = $this->model->showForeignKeys($this->table, 'parent_id');

                    if($parent)
                    {
                        if($this->table === $parent['REFERENCED_TABLE_NAME'])
                        {
                            $where = 'parent_id IS NULL OR parent_id = 0';
                        }else{
                            $columns = $this->model->showColumns($parent['REFERENCED_TABLE_NAME']);

                            if($columns['parent_id']) $order[] = 'parent_id';
                            else $order[] = $parent['REFERENCED_COLUMN_NAME'];

                            $id = $this->model->get($parent['REFERENCED_TABLE_NAME'], [
                                'fields' => [$parent['REFERENCED_COLUMN_NAME']],
                                'order'  => $order,
                                'limit'  => 1
                            ])[0][$parent['REFERENCED_COLUMN_NAME']];

                            if($id) $where = ['parent_id' => $id];
                        }
                    }else{

                        $where = 'parent_id IS NULL OR parent_id = 0';

                    }
                }
            }

            //$iteration = (int)!$this->data;  если в $this->data что-то пришло, то НЕ$this->data вернёт false(0), а если
            //                                 в $this->data ничего не пришло, то НЕ$this->data вернёт true(1)

            $menu_pos = $this->model->get($this->table, [
                    'fields'    => ['COUNT(*) as count'],// COUNT(*) as count - посчитай все и
                    // предоставь выборку с псевдонимом COUNT
                    'where'     => $where,
                    'no_concat' => true
                ])[0]['count'] + (int)!$this->data;

            for($index = 1; $index <= $menu_pos; $index++)
            {
                $this->foreignData['menu_position'][$index - 1]['id'] = $index;
                $this->foreignData['menu_position'][$index - 1]['name'] = $index;
            }

        }
        return;
    }

    protected function checkOldAlias($id)
    {

        $tables = $this->model->showTables();

        if(in_array('old_alias', $tables))
        {

            $old_alias = $this->model->get($this->table, [
                'fields' => ['alias'],
                'where'  => [$this->columns['id_row'] => $id]
            ])[0]['alias'];

            if($old_alias && $old_alias !== $_POST['alias']){

                $this->model->delete('old_alias', [
                    'where' => ['alias' => $old_alias, 'table_name' => $this->table]
                ]);

                $this->model->delete('old_alias', [
                    'where' => ['alias' => $_POST['alias'], 'table_name' => $this->table]
                ]);

                $this->model->add('old_alias', [
                    'fields' => ['alias' => $old_alias, 'table_name' => $this->table,
                        'table_id' => $id],

                ]);

            }

        }

    }

    protected function checkFiles($id)
    {

        if($id && $this->fileArray)
        {

            // $data - здесь получаем файлы которые УЖЕ имеютсяна на сервере и хранятся в БД
            $data = $this->model->get($this->table, [
                'fields' => array_keys($this->fileArray),
                'where'  => [$this->columns['id_row'] => $id]
            ]);

            if($data)
            {

                $data = $data[0];

                foreach ($this->fileArray as $key => $item)
                {

                    if(is_array($item) && !empty($data[$key]))
                    {

                        $fileArr = json_decode($data[$key]);

                        if($fileArr)
                        {

                            foreach ($fileArr as $file)
                            {
                                $this->fileArray[$key][] = $file;
                            }

                        }

                    }elseif(!empty($data[$key])){

                        $checkTest = $_SERVER['DOCUMENT_ROOT'] . PATH . UPLOAD_DIR . $data[$key];

                        @unlink($_SERVER['DOCUMENT_ROOT'] . PATH . UPLOAD_DIR . $data[$key]);

                    }

                }

            }

        }

    }
}