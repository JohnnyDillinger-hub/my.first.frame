<?php


namespace core\admin\controller;


use core\base\controller\BaseMethods;

class CreatesitemapController extends BaseAdmin
{

    use BaseMethods;

    protected $all_links  = []; // массив сo ссылками
    protected $temp_links = []; // временные ссылки при парсинге
    protected $bad_links  = [];

    protected $maxLinks = 5000; // максимальное количество ссылок дотупных для парсинга
    protected $parsingLogFile = 'parsingLog.txt';
    protected $fileArr = ['jpg', 'png', 'jpeg', 'gif', 'xls', 'xlsx', 'pdf', 'mp4', 'mp3'];

    protected $filterArr = [
        'url' => [], // икслючение по URL-параметру
        'get' => []         // исключение по GET-параметру
    ];

    // $links_counter - делитель, на который будем делить число максимально доступных ссылок для парсинга
    public function inputData($links_counter = 1, $redirect = true)
    {

        $links_counter = $this->clearNum($links_counter);

        // если не произошла инициализация CURL, то выкидываем в JS ошибку
        if(!function_exists('curl_init')){
            $this->cancel(0, 'Library CURL as apset. Creation of sitemap imposible', '', true);
        }

        // инициализируем объект модели для работы с базой данных
        if(!$this->userId) $this->execBase();

        // если метод не отработал, то выкидываем в JS ошибку
        if(!$this->checkParsingTable()) {
            $this->cancel(0, 'You have a problem with database table parsing_data', '', true);
        }

        // снимаем ограничение по времени для отработки скрипта
        set_time_limit(0);

        // делаем запрос в БД, чтобы получить таблицу с данными полученными при парсинге (в случае ошибки при парсинге)
        $reserve = $this->model->get('parsing_data')[0];

        $table_rows = [];

        foreach ($reserve as $name => $item)
        {

            $table_rows[$name] = '';

            if($item) $this->$name = json_decode($item);
                elseif($name === 'all_links' || $name === 'temp_links') $this->$name = [SITE_URL];
        }

        // ceil() - округляет дробь в большую сторону
        // делим лимит на делитель, если сервер упал от переполнения
        $this->maxLinks = (int)$links_counter > 1 ? ceil($this->maxLinks / $links_counter) : $this->maxLinks;

        // пока в temp_links что-то есть - мы продолжаем работать
        while($this->temp_links){

            $temp_links_count = count($this->temp_links);

            $links = $this->temp_links;

            $this->temp_links = [];

            if($temp_links_count > $this->maxLinks){

                /*
                array_chunk() — разбивает массив на части
                1 аргумент    - массив который делим
                2 аргумент    - колличесво элементов которые хотим получить в конце
                */
                $links = array_chunk($links, ceil($temp_links_count / $this->maxLinks));

                // сохраняем колличество элемнтов массива $links после дробления его на куски
                $count_chunks = count($links);

                // проводим парсинг каждого отдельного куска массива, чтобы сервер не упал
                for($i = 0; $i < $count_chunks; $i++)
                {
                    $this->parsing($links[$i]);

                    unset($links[$i]);

                    if($links){

                        foreach ($table_rows as $name => $item)
                        {
                            if($name === 'temp_links')
                            {
                                /*
                                array_merge() принимает в аргументы массивы, но у нас есть только $links, но его элементы
                                так же являются массивами, поэтому используем диструктивное присваивание, чтобы
                                в array_merge() были переданы те самые массивы, что вложены в $links
                                */
                                $table_rows[$name] = json_encode(array_merge(...$links));
                            }else{
                                $table_rows[$name] = json_encode($this->$name);
                            }
                        }

                        // ...$links - распаковывает массив в переменные
                        //array_merge() — сливает воедино один или большее количество массивов
                        $this->model->edit('parsing_data', [
                            'fields' => $table_rows
                        ]);
                    }
                }

            }else{

                $this->parsing($links);

            }

            foreach ($table_rows as $name => $item)
            {
                $table_rows[$name] = json_encode(array_merge(...$links));
            }

            $this->model->edit('parsing_data', [
                'fields' => $table_rows
            ]);
        }

        foreach ($table_rows as $name => $item)
        {
            $table_rows[$name] = '';
        }

        // чистим БД, чтобы можно было повторно производить парсинг
        $this->model->edit('parsing_data', [
           'fields' => $table_rows
        ]);

        if($this->all_links){
            foreach ($this->all_links as $key => $link){

                if(!$this->filter($link) || in_array($link, $this->bad_links)) unset($this->all_links[$key]);

            }
        }

        $this->createSitemap();

        if($redirect)
        {
            !$_SESSION['res']['answer'] && $_SESSION['res']['answer'] = '<div class="success">Sitemap is created</div>';

            $this->redirect();
        }else{

            $this->cancel(1, 'Sitemap is created! ' . count($this->all_links) . ' links', '', true);

        }

    }

    // непосредственно сам метод для парсинга
    protected function parsing($urls)
    {

        if(!$urls) return;
        
        $curlMulty = curl_multi_init();
        
        $curl = [];
        
        foreach ($urls as $i => $url)
        {
            
            $curl[$i] = curl_init();
            //curl_setopt() — Устанавливает параметр для сеанса CURL
            curl_setopt($curl[$i], CURLOPT_URL, $url);
            curl_setopt($curl[$i], CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl[$i], CURLOPT_HEADER, true);
            curl_setopt($curl[$i], CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($curl[$i], CURLOPT_TIMEOUT, 120);
            /*
             * Браузер получает страницу сайта в формате gzip, декодирует её и показывает пользователю,
             * но если не выставить настройку CURLOPT_ENCODING, то не сработает ни одна из регулярок,
             * поэтому CURL'у явно указываем, что надо декодировать страницу перед её парсингом
            */
            curl_setopt($curl[$i], CURLOPT_ENCODING, 'gzip,deflate');

            //curl_multi_add_handle — Добавляет обычный cURL-дескриптор к набору cURL-дескрипторов
            curl_multi_add_handle($curlMulty, $curl[$i]);
        }

        // проверяем каждый дескриптор cURl на наличие ошибок
        do{
            /*
             * curl_multi_exec — Запускает подсоединения текущего дескриптора cURL
             * 1 аргумент - Мультидескриптор cURL, полученный из curl_multi_init().
             * 2 аргумент - Ссылка на флаг, указывающий, идут ли ещё какие-либо действия (возвращает 0, если все
             * cURL-дескрипторы отработали)
             */
            $status = curl_multi_exec($curlMulty, $active);
            /*
             * curl_multi_info_read — Возвращает информацию о текущих операциях
             * Опрашивает набор дескрипторов о наличии сообщений или информации от индивидуальных передач.
             * Сообщения могут включать такую информацию как код ошибки передачи или просто факт завершения передачи.
             *
             * Повторяющиеся вызовы этой функции будут каждый раз возвращать новый результат, пока не будет возвращено
             * false в качестве сигнала окончания сообщений. Целое число, содержащееся в queued_messages, указывает
             * количество оставшихся сообщений после вызова данной функции.
             */
            $info   = curl_multi_info_read($curlMulty);

            if(false !== $info)
            {
                if($info['result'] !== 0)
                {
                    $i = array_search($info['handle'], $curl);

                    $error = curl_errno($curl[$i]); // вернётся номер ошибки
                    $errorMessage = curl_error($curl[$i]); // вернёт сообщение об ошибке
                    $header = curl_getinfo($curl[$i]); // получем информацию о настройках текущего дескриптора

                    if($error != 0){

                        // записываем сообщение об ошибке в лог
                        $this->cancel(0, 'Error loading ' . $header['url'] .
                                                          ' http code: ' . $header['http_code'].
                                                           ' error: ' . $error . ' messages' . $errorMessage);
                    }
                }
            }

            if($status > 0){

                $this->cancel(0, curl_multi_strerror($status));

            }

        }while($status === CURLM_CALL_MULTI_PERFORM || $active);

        $result = [];

        foreach ($urls as $i => $url)
        {

            // получаем результат работы cURL
            $result[$i] = curl_multi_getcontent($curl[$i]);

            /*
             * curl_multi_remove_handle — Удаляет cURL дескриптор из набора cURL дескрипторов
             * 1 аргумент - Мультидескриптор cURL, полученный из curl_multi_init().
             * 2 аргумент - Дескриптор cURL, полученный из curl_init().
             */
            curl_multi_remove_handle($curlMulty, $curl[$i]);
            // закрываем поток cURL
            curl_close($curl[$i]);

            if(!preg_match("/Content-Type:\s+text\/html/ui", $result[$i])) {

                $this->bad_links[] = $url;

                $this->cancel(0, 'Incorrect content type ' . $url);

                continue;

            }

            // проверяем код ответа сервера, должен быть 200 или 20+
            if(!preg_match("/HTTP\/\d\.?\d?\s+20\d/ui", $result[$i])){

                $this->bad_links[] = $url;

                $this->cancel(0, 'Incorrect server code ' . $url);

                continue;
            }

            $this->createLinks($result[$i]);
        }

        // завершаем роботу многопоточного cCURL
        curl_multi_close($curlMulty);

    }

    protected function createLinks($content){

        //$str = '<link class="class" id="1" href="segvesgeva" data-id="faefawd">';

        //preg_match_all() - ищет все вхождения подстроки в строку
        // 1 аргумент - шаблон регулярного выражения
        // 2 аргумент - где ищем
        // 3 аргумент - где будут хранится все ссылки

        /* Регулярные выражения
        \s - пробел
         * - ноль или более раз
         ? - поиск в меньную сторону
         [^*символ*] - кроме
         $ - конец строки
         */
        preg_match_all( '/<a\s*?[^>]*?href\s*?=\s*?(["\'])(.+?)\1[^>]*?>/ui', $content, $links);

        if($links[2]){

            foreach ($links[2] as $link)
            {
                if($link === '/' || $link === SITE_URL . '/') continue;

                /*
                fileArr - массив со всеми нужными расширениями файлов
                $ext - расширение
                */
                foreach ($this->fileArr as $ext)
                {
                    if($ext)
                    {
                        $ext = addslashes($ext); // экранируем содержание
                        $ext = str_replace('.', '\.', $ext); // экранируем все точки в строке

                        if(preg_match('/' . $ext . '(\s*?$|\?[^\/]*$)/ui', $link))
                        {

                            continue 2;

                        }
                    }
                }

                // ссылка относительная или абсолютная
                if(strpos($link, '/') === 0){
                    $link = SITE_URL . $link;
                }

                // экранируем все метасимволы в адресе сайта, который мы будем парсить
                $site_url = str_replace('.', '\.',
                    str_replace('/', '\/', SITE_URL));

                // если в bad_links нет обрабатываемой ссылки И если она не проходит по регулярке И если корень сайта
                // имеется в начале оной И если её нет в массиве всех ссылок
                if(!in_array($link, $this->bad_links) && !preg_match('/^(' . $site_url . ')?\/?#[^\/]*?$/ui', $link) &&
                    strpos($link, SITE_URL) === 0 && !in_array($link, $this->all_links)){

                    $this->temp_links[] = $link;
                    $this->all_links[] = $link;
                }
            }
        }
    }

    // метод для фильтрации ссылок для парсера
    protected function filter($link)
    {

        if($this->filterArr) // существуют ли вообще исключения
        {
            /*
            $type       - url или get исключение(массив)
            $typeValues - искомое исключение(массив)
            */
            foreach ($this->filterArr as $arrType => $arrValues)
            {
                if($arrValues)
                {

                    //$value - каждое отдельное исключение
                    foreach ($arrValues as $value) {

                        // если получили значение с косой чертой
                        $value = str_replace('/', '\/', addslashes($value));

                        // если $type равно url
                        if($arrType === 'url')
                        {
                            if(preg_match('/^[^\?]*' . $value . '/ui', $link)) return false;
                        }

                        // если $type равно get
                        if($arrType === 'get')
                        {

                            if(preg_match('/(\?|&amp;|=|&)' . $value . '(=|$amp;|&|$)/ui', $link))
                            {
                                return false;
                            }

                        }
                    }
                }
            }
        }

        return true;

    }

    // проверяет, созданна ли таблица для парсинга
    protected function checkParsingTable()
    {

        $tables = $this->model->showTables();

        // если этой таблицы нет в базе данных
        if(!in_array('parsing_data', $tables))
        {

            // создаем новую талицу с указанными полями
            $query = 'CREATE TABLE parsing_data (all_links text, temp_link longtext, bad_links longtext)';

            // если запрос не сработал, то вернуть false
            if(!$this->model->query($query, 'c') ||
                !$this->model->add('parsing_data', ['fields' => ['all_links' => '', 'temp_links' => '',
                    'bad_links' => '']]))
            { return false; }

        }

        return true;
    }

    protected function cancel($success = 0, $message = '', $log_message = '', $exit = false)
    {

        // массив который будем отдавать клиенту
        $exitArr = [];

        $exitArr['success'] = $success;
        $exitArr['message'] = $message ? $message : 'ERROR_PARSING';
        $log_message = $log_message ? $log_message : $exitArr['message'];

        // класс для отображения пользователю ошибки или успешности выполнения парсинга
        $class = 'success';

        if(!$exitArr['success'])
        {
            $class = 'error';

            // если success = false, то записываем информацию об ошибке
            $this->writeLog($log_message, 'parsing_log.txt');
        }

        if($exit)
        {
            $exitArr['message'] = '<div class="' . $class . '">' . $exitArr['message'] . '</div>';
            exit(json_encode($exitArr));
        }
    }

    // метод, который будет строить карту сайта что будем парсить
    protected function createSitemap()
    {

        $dom = new \domDocument('1.0', 'utf-8');
        /*
         * Форматирует вывод, добавляя отступы и дополнительные пробелы. Не работает, если документ был
         * загружен с включённым параметром preserveWhitespace.
         */
        $dom->formatOutput = true;

        // Создаем корневой элемент класса DOMDocument
        // urlset - навзание элемента
        $root = $dom->createElement('urlset');
        $root->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $root->setAttribute('xmlns:xls', 'http://w3.org/2001/XMLSchema-instance');
        $root->setAttribute('xsi:schemaLocation', 'http://www.sitemaps.org/schemas/sitemap/0.9
                                                                      http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd');

        //appendChild() - Добавляет новый дочерний узел в конец списка потомков
        $dom->appendChild($root);

        $sxe = simplexml_import_dom($dom);

        if($this->all_links)
        {
            $date = new \DateTime();
            $lastMode = $date->format('Y-m-d') . 'T' . $date->format('H:i:s+01:00');

            foreach ($this->all_links as $item)
            {



                $elem = trim(mb_substr($item, mb_strlen(SITE_URL)), '/');
                $elem = explode('/', $elem);

                $count = '0.' . (count($elem) - 1);
                $priority = 1 - (float)$count;

                if($priority == 1) $priority = '1.0';

                // добавляем потомка
                $urlMain = $sxe->addChild('url');

                $urlMain->addChild('loc', htmlspecialchars($item));
                $urlMain->addChild('lastmod', $lastMode);
                $urlMain->addChild('changefreg', 'weekly');
                $urlMain->addChild('priority', $priority);
            }

            $dom->save($_SERVER['DOCUMENT_ROOT'] . PATH . 'sitemap.xml');

        }
    }
}