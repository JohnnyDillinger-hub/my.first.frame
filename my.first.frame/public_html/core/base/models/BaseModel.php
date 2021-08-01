<?php


namespace core\base\models;

use core\base\exceptions\DbException;

abstract class BaseModel extends BaseModelMethods
{

    protected $db; // объект подключения к базе данных

    protected function connectDB()
    {
        $this->db = @new \mysqli(HOST, USER, PASS, DB_NAME);

        if($this->db->connect_error)
        {
            throw new DbException("Ошибка подключения к базе данных: "
                                   . $this->db->connect_errno . ' ' . $this->db->connect_error);
        }

        /*query() - функция выполняет запрос к базе данных*/
        // отправляем базе данных информацию о том, в какой кодировке будем производить общение
        $this->db->query("SET NAMES UTF8");
    }

    // final - директива

    /**
     * @param $query
     * @param string $crud = r - SELECT / c - INSERT / u - UPDATE / d - DELETE
     * @param false $return_id
     * @return array|bool|mixed
     * @throws DbException
     */
    final public function query($query, $crud = "r", $return_id = false)
    {

        // делаем запрос к базе данных
        $result = $this->db->query($query);

        $db_error = mysqli_sqlstate($this->db);

        // если выдало ошибку

        //if($this->db->affected_rows === -1)
        if(mysqli_sqlstate($this->db) !== '00000')
        {
            throw new DbException("Ошибка в SQL-запросе: "
                . $query . " - " . $this->db->errno . " " . $this->db->error
            );
        }

        switch ($crud)
        {
            case 'r':

                if($result->num_rows)
                {
                    $res = [];

                    for($index = 0; $index < $result->num_rows; $index++)
                    {
                        //fetch_assoc() - извлекает результирующий ряд в виде ассоциативного массива
                        $res[] = $result->fetch_assoc();
                    }

                    return $res;
                }

                return false;

                break;

            case 'c':

                if($return_id) return $this->db->insert_id;

                return true;

                break;

            default:

                return true;

                break;
        }

    }

    /**
     * @param $table - таблицы базы данных
     * @param array $set
     * [
    "fields"          => ["id", "name"],
    "no_concat"       => false/true - Если true, то присоединять имятаблицы к полям и where
    "where"           => ["name" => "Masha, Olga, Vika", "surname" => "Sergeevna"],
    "operand"         => ["<>", "="],
    "condition"       => ["AND"],
    "order"           => ["fio", "name"],
    "order_direction" => ["ASC", "DESC"],
    "limit"           => '1',
    "join"              => [
        "table"             => 'join_table1',
        "fields"            => ["id as j_id", "name as j_name"],
        "type"              => "left",
        "where"             => ['name' => "Sasha"],
        "operand"           => ['='],
        "condition"         => ["OR"],
        "on"                => ["id", "parent_id"],
        "group_condition"   => "AND"
    ],
        "join_table2"       => [
            "table"             => 'join_table2',
            "fields"            => ["id as j2_id", "name as j2_name"],
            "type"              => "left",
            "where"             => ['name' => "Sasha"],
            "operand"           => ['='],
            "condition"         => ["AND"],
                "on"                => [
                "table"             => "teachers",
                "fields"            => ["id", "parent_id"]
            ]
        ]
    ]
     */
    final public function get($table, $set = [])
    {

        $fields = $this->createFields($set, $table);

        $order = $this->createOrder($set, $table);

        $where = $this->createWhere($set, $table);

        if(!$where) $new_where = true;
            else $new_where = false;

        $join_arr = $this->createJoin($set, $table, $new_where);

        $fields .= $join_arr["fields"];
        $join    = $join_arr["join"];
        $where  .= $join_arr["where"];

        $fields = rtrim($fields, ',');

        $limit = $set["limit"] ? "LIMIT " . $set["limit"] : '';

        $query = "SELECT $fields FROM $table $join $where $order $limit";

        $res = $this->query($query);

        if(isset($set['join_structure']) && $set['join_structure'] && $res)
        {

            $res = $this->joinStructure($res, $table);

        }

        return $res;

    }

    /**

     * @param $table - таблица для вставки данных
     * @param array $set - массив параметров:
     * fields => [поле => значение]; - если не указан, то обрабатывается $_POST[поле => значение]
     * разрешена передача NOW() в качестве MySql функции обычной строкой
     * files => [поле => значение]; - можно подать массив вида [поле => [массив значений]]
     * except => ['исключение 1', 'исключение 2'] - исключает данные элементы массива из добавленных в запрос
     * return_id => true | false - возвращать или нет идентификатор вставленной записи
     *@return mixed
     */

    final public function add($table, $set = [])
    {

        $set["fields"] = (is_array($set["fields"]) && !empty($set["fields"])) ? $set["fields"] : $_POST;
        $set["files"] = (is_array($set["files"]) && !empty($set["files"])) ? $set["files"] : false;

        // если нечего добавлять
        if(!set['fields' && !$set['files']]) return false;

        $set["return_id"] = $set["return_id"] ? true : false;
        $set["except"] = (is_array($set["except"]) && !empty($set["except"])) ? $set["except"] : false;

        $insert_arr = $this->createInsert($set['fields'], $set['files'], $set["except"]);


        $input_query = "INSERT INTO $table {$insert_arr['fields']} VALUES {$insert_arr['values']}";

        return $this->query($input_query, 'c', $set['return_id']);

    }

    // метод для обновления данных в БД
    final public function edit($table, $set = [])
    {
        $set["fields"]   = (is_array($set["fields"]) && !empty($set["fields"])) ? $set["fields"] : $_POST;
        $set["files"]    = (is_array($set["files"]) && !empty($set["files"])) ? $set["files"] : false;

        // если нечего добавлять
        if(!$set['fields'] && !$set['files']) return false;

        $set["except"]   = (is_array($set["except"]) && !empty($set["except"])) ? $set["except"] : false;

        $where = '';

        if(!$set["all_rows"])
        {

            if($set["where"])
            {
                $where = $this->createWhere($set);
            }else{

                $columns = $this->showColumns($table);

                if(!$columns) return false;

                if($columns['id_row'] && $set["fields"][$columns["id_row"]])
                {
                    $where = 'WHERE ' . $columns["id_row"] . '=' . $set["fields"][$columns["id_row"]];
                    unset($set["fields"][$columns["id_row"]]);
                }
            }

        }

        $update = $this->createUpdate($set['fields'], $set['files'], $set["except"]);

        $query = "UPDATE $table SET $update $where";
        
        return $this->query($query, 'u');
    }

    /**
     * @param $table - таблицы базы данных
     * @param array $set
     * [
    "fields"          => ["id", "name"],
    "where"           => ["name" => "Masha, Olga, Vika", "surname" => "Sergeevna"],
    "operand"         => ["<>", "="],
    "condition"       => ["AND"],
    "join"              => [
    "table"             => 'join_table1',
    "fields"            => ["id as j_id", "name as j_name"],
    "type"              => "left",
    "where"             => ['name' => "Sasha"],
    "operand"           => ['='],
    "condition"         => ["OR"],
    "on"                => ["id", "parent_id"],
    "group_condition"   => "AND"
    ],
    "join_table2"       => [
    "table"             => 'join_table2',
    "fields"            => ["id as j2_id", "name as j2_name"],
    "type"              => "left",
    "where"             => ['name' => "Sasha"],
    "operand"           => ['='],
    "condition"         => ["AND"],
    "on"                => [
    "table"             => "teachers",
    "fields"            => ["id", "parent_id"]
    ]
    ]
    ]
     */

    public function delete($table, $set = [])
    {

        $table = trim($table);
        $where = $this->createWhere($set, $table);

        $columns = $this->showColumns($table);

        if(!$columns) return false;

        if(is_array($set['fields']) && !empty($set['fields']))
        {

            if($columns['id_row'])
            {
                $key = array_search($columns['id_row'], $set['fields']);
                if($key !== false) unset($set['fields'][$key]);
            }

            $fields = [];

            foreach ($set['fields'] as $field)
            {
                $fields[$field] = $columns[$field]['Default'];
            }

            $update = $this->createUpdate($fields, false, false);

            $query = "UPDATE $table SET $update $where";

        }else{

            $join_arr = $this->createJoin($set, $table);
            $join = $join_arr['join'];
            $join_tables = $join_arr['tables'];

            $query = "DELETE " . $table . $join_tables . ' FROM ' . $table . ' ' . $join . ' ' .$where;

        }

        return $this->query($query, 'u');

    }

    final public function showColumns($table)
    {
        if(!isset($this->tableRows[$table]) || !$this->tableRows[$table])
        {

            $chekTable = $this->createTableAlias($table);

            if($this->tableRows[$chekTable['table']])
            {

                return $this->tableRows[$chekTable['alias']] = $this->tableRows[$chekTable['table']];

            }

            $query = "SHOW COLUMNS FROM {$chekTable['table']}";

            $res = $this->query($query);

            $this->tableRows[$chekTable['table']] = [];

            if($res)
            {

                foreach ($res as $row)
                {
                    $this->tableRows[$chekTable['table']][$row['Field']] = $row;

                    // если это первичный ключ
                    if($row['Key'] === 'PRI')
                    {

                        if(!$this->tableRows[$chekTable['table']]['id_row'])
                        {

                            $this->tableRows[$chekTable['table']]['id_row'] = $row['Field'];

                        }else{

                            if(!isset($this->tableRows[$chekTable['table']]['multi_id_row']))
                            {
                                $this->tableRows[$chekTable['table']]['multi_id_row'][] =
                                    $this->tableRows[$chekTable['table']]['id_row'];
                            }

                            // здесь будет массив первичных ключей
                            $this->tableRows[$chekTable['table']]['multi_id_row'][] = $row['Field'];

                        }
                    }
                }
            }
        }

        if(isset($chekTable) && $chekTable['table'] !== $chekTable['alias'])
        {
            return $this->tableRows[$chekTable['alias']] = $this->tableRows[$chekTable['table']];
        }

        return $this->tableRows[$table];

    }

    final public function showTables()
    {

        $query = 'SHOW TABLES';

        $tables = $this->query($query);

        $table_arr = [];

        if($tables)
        {
            foreach ($tables as $table)
            {

                $table_arr[] = reset($table);

            }
        }

        return $table_arr;
    }
}

