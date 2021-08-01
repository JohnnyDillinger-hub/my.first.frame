<?php


namespace core\base\models;


abstract class BaseModelMethods
{

    protected $sqlFunc = ['RAND()','NOW()'];
    protected $tableRows;

    protected function createFields($set, $table = false, $joinFlag = false)
    {
//        // если в ячейке "fields" пусто, то ставим "*" (все)
//        $set["fields"] = (is_array($set["fields"]) && !empty($set["fields"])) ? $set["fields"] : ["*"];
//
//        // есть в "table" пусто, то сохраняем пустое значение
//        $table = ($table && !$set['no_concat']) ? $table . "." : "";
//
//        $fields = '';
//
//        foreach ($set["fields"] as $field)
//        {
//            $fields .= $table . $field . ",";
//        }
//
//        return $fields;

        if(array_key_exists('fields', $set) && $set['fields'] === null)
        {
            return '';
        }

        $concatTable = '';
        $aliasTable = $table;

        if(!$set['no_concat'])
        {

            $arr = $this->createTableAlias($table);

            $concatTable = $arr['alias'] . '.';

            $aliasTable = $arr['alias'];

        }

        $fields = '';

        $joinStructure = false;

        if(($joinFlag || isset($set['join_structure']) && $set['join_structure']) && $table)
        {

            $joinStructure = true;

            $this->showColumns($table);

            if(isset($this->tableRows[$table]['multi_id_row'])) $set['fields'] = [];

        }

        // $concatTable = $table && !$set['no_concat'] ? $table . '.' : '';

        // формируем строку, если в ячейки fields ничего нет
        if(!isset($set['fields']) || !is_array($set['fields']) || !$set['fields'])
        {

            if(!$joinFlag)
            {

                $fields = $concatTable . '*,';

            }else{

                foreach ($this->tableRows[$aliasTable] as $key => $item) {

                    if ($key !== 'id_row' && $key !== 'multi_id_row')
                    {
                        $fields .= $concatTable . $key . ' as TABLE' . $aliasTable . 'TABLE_' . $key . ',';
                    }
                }
            }

        }else{

            $idField = false;

            foreach ($set['fields'] as $field)
            {
                if($joinStructure && !$idField && $this->tableRows[$aliasTable] === $field)
                {
                    $idField =true;
                }

                if($field)
                {
                    if ($joinFlag && $joinStructure)
                    {

                        /*
                         * '/^(.+)?\s+as\s+(.+)/i' - ищем сначала строки, любые символы до пробела сколько угодно раз,
                         * после пробела обязательно "as", затем пробелы сколько угодно раз и после любые символы сколько
                         * угодно раз
                         * */
                        if(preg_match('/^(.+)?\s+as\s+(.+)/i', $field, $matches))
                        {
                            $fields .= $concatTable . $matches[1] . ' as TABLE' . $aliasTable . 'TABLE_' . $matches[2] . ',';
                        }else{
                            $fields .= $concatTable . $field . ' as TABLE' . $aliasTable . 'TABLE_' . $field . ',';
                        }

                    }else{

                        $fields .= $concatTable . $field . ',';

                    }
                }
            }

            if(!$idField && $joinStructure)
            {

                if($joinFlag)
                {

                    $fields .= $concatTable . $this->tableRows[$aliasTable]['id_row'] . ' as TABLE' . $aliasTable .
                                                'TABLE_' . $this->tableRows[$aliasTable]['id_row'] . ',';

                }else{

                    $fields .= $concatTable . $this->tableRows[$aliasTable]['id_row'] . ',';

                }
            }
        }

        return $fields;

    }

    protected function createOrder($set, $table = false)
    {
        $table = ($table && (!isset($set['no_concat']) || !$set['no_concat'])) ?
            $this->createTableAlias($table)['alias'] . "." : "";

        $order = "";

            if(isset($set['order']) && $set['order'])
            {
                $set['order'] = (array)$set['order']; //если придёт просто строка, то приведение типов просто
                                                      // закинет её в нулевой элемент массива

                // если ячейка $set["order_direction"] существует и является массивом, то сохраняем её
                $set['order_direction'] = (isset($set['order_direction']) && $set['order_direction'])
                    ? (array)$set["order_direction"] : ["ASC"];

                $direct_count = 0;
                $order_by     = "ORDER BY ";

                foreach ($set['order'] as $order)
                {
                    if($set['order_direction'][$direct_count])
                    {
                        // strtoupper() - преобразует строку в верхний регистр
                        $order_direction = strtoupper($set['order_direction'][$direct_count]);
                        $direct_count++;
                    }else{
                        $order_direction = strtoupper($set['order_direction'][$direct_count - 1]);
                    }

                    if(in_array($order, $this->sqlFunc)) $order_by .= $order . ', ';
                    elseif(is_int($order)) $order_by .= $order . ' ' . $order_direction . ", ";
                    else $order_by .= $table . $order . ' ' . $order_direction . ", ";

                }
            }

            $order_by = rtrim($order_by, ', ');

            return $order_by;

    }

    protected function createWhere($set, $table = false, $instruction = 'WHERE')
    {
        $table = ($table && (!isset($set['no_concat']) || !$set['no_concat'])) ?
            $this->createTableAlias($table)['alias'] . "." : ""; //таблица из который производится выборка

        $where = '';

        // если пришла строка
        if(is_string($set['where']))
        {
            return $instruction . ' ' . trim($set['where']);
        }

        // если ячейка $set["where"] существует и является массивом, то сохраняем её
        if((is_array($set["where"]) && !empty($set["where"])))
        {
            $set["operand"] = is_array($set["operand"]) && !empty($set["operand"])
                ? $set["operand"] : ['='];

            $set["condition"] = is_array($set["condition"]) && !empty($set["condition"])
                ? $set["condition"] : ['AND'];

            $where = $instruction; //сохраняем инструкцию переданную в параметры метода

            $o_count = 0; //переменная для перебора ячеек подмассива $set['operand']
            $c_count = 0; //переменная для перебора ячеек подмассива $set['condition']

            // "where"   => ["id" => 1, "name" => "Masha"]
            foreach ($set["where"] as $key => $value)
            {
                $where .= ' ';

                if($set['operand'][$o_count])
                {
                    $operand = $set['operand'][$o_count];
                    $o_count++;
                }else{
                    $operand = $set['operand'][$o_count - 1];
                }

                if($set['condition'][$c_count])
                {
                    $condition = $set['condition'][$c_count];
                    $c_count++;
                }else{
                    $condition = $set['condition'][$c_count - 1];
                }

                if($operand === 'IN' || $operand === 'NOT IN')
                {
                    if(is_string($value) && strpos($value, 'SELECT') === 0)
                    {
                        $in_str = $value;
                    }else{
                        if(is_array($value)) $temp_value = $value;
                        else $temp_value = explode(",", $value);

                        $in_str = '';

                        foreach ($temp_value as $v)
                        {
                            $in_str .= "'" . trim($v, ',') . "',";
                        }
                    }

                    $where .= $table . $key . ' ' . $operand . ' (' . rtrim($in_str, ',') . ") " . $condition;

                }elseif(strpos($operand, 'LIKE') !== false) {

                    $like_temp = explode('%', $operand);

                    foreach ($like_temp as $lt_key => $lt_value)
                    {
                        if(!$lt_value)
                        {
                            if(!$lt_key) //если $lt_key пуст, значит знак % стоял первым
                            {
                                $value = '%' . $value;
                            }else{
                                $value .= '%';
                            }
                        }
                    }

                    $where .= $table . $key . ' LIKE ' . "'" . addslashes($value) . "' $condition";

                }else{

                    if(strpos($value, 'SELECT') === 0)
                    {
                        $where .= $table . $key . " " . $operand . " (" . $value . ") " . $condition;
                    }elseif($value === null || $value === 'NULL'){

                        if($operand === '=') $where .= $table . $key . ' IS NULL ' . $condition;
                            else $where .= $table . $key . ' IS NOT NULL ' . $condition;

                    }else{
                        $where .= $table . $key . " " . $operand . " '" . addslashes($value) . "' " . $condition;
                    }

                }
            }

            $where = substr($where, 0, strrpos($where, $condition));
        }

         return $where;
    }

    protected function createJoin($set, $table, $new_where = false)
    {
        $fields     = ""; // переменная для передачи массива полей
        $join       = ""; //
        $where      = "";
        // $tables     = "";
        if($set["join"])
        {

            $join_table = $table;

            foreach ($set["join"] as $key => $value)
            {
                if(is_int($key)) // если индекс ячейки имеет тип int
                {
                    if(!$value["table"]) continue; // если нет значения "table" => 'join_table1'(это значение)
                    else $key = $value["table"];
                }

                $concatTable = $this->createTableAlias($key)['alias'];

                if($join) $join .= " "; // если в $join уже что-то есть, то контентенируем к ней пробел

                if(isset($value['on']) && $value['on'])
                {
                    if(isset($item['on']['fields']) && is_array($item['on']['fields']) &&
                        count($item['on']['fields']) === 2)
                    {

                        $join_fields = $value['on']["fields"];

                    }elseif(count($value['on']) === 2) {

                        $join_fields = $value['on'];

                    }else{

                        continue;

                    }

//                    switch (2)
//                    {
//                        case (is_array(($value['on']["fields"])) && count($value['on']["fields"])):
//
//                            $join_fields = $value['on']["fields"];
//
//                            break;
//
//                        case (is_array($value['on']) && count($value['on'])):
//
//                            $join_fields = $value['on'];
//
//                            break;
//
//                        default:
//                            continue 2; // выведет на второй уровень цикла (switch - первый, foreach - второй)
//                    }

                    if(!$value["type"]) $join .= "LEFT JOIN ";
                    else $join .= trim(strtoupper($value["type"])) . " JOIN ";

                    $join .= $key . " ON ";

                    if($value['on']["table"]) $joinTempTable = $value["table"];
                    else $joinTempTable = $join_table;

                    $join .= $this->createTableAlias($joinTempTable)['alias'];

                    $join .= "." . $join_fields[0] . "=" . $concatTable . "." . $join_fields[1];

                    $join_table = $key;
                    // $tables .= ', ' . trim($join_table);

                    if($new_where)
                    {
                        if($value["where"])
                        {
                            $new_where = false;
                        }

                        $group_condition = "WHERE";
                    }else{
                        $group_condition = $value["group_condition"] ? strtoupper($value["group_condition"]) : "AND";
                    }

                    $fields .= $this->createFields($value,$key, $set['join_structure']);
                    $where  .= $this->createWhere($value, $key, $group_condition);
                }
            }

        }

        return compact('fields', 'join', 'where');
    }

    protected function createInsert($fields, $files, $except)
    {

        $insert_arr = [];

        $insert_arr['fields'] = '(';

        $array_type = array_keys($fields)[0];

        if(is_int($array_type))
        {
            $check_fields = false;
            $count_fields = 0;

            foreach ($fields as $index => $item)
            {
                $insert_arr['values'] .= '(';

                if(!$count_fields) $count_fields = count($fields[$index]);

                $j = 0;

                foreach ($item as $key => $value)
                {
                    if($except && in_array($key, $except)) continue;

                    if(!$check_fields) $insert_arr['fields'] .= $key . ',';

                    if(in_array($value, $this->sqlFunc))
                    {
                        $insert_arr['values'] .= $value . ',';

                    }elseif($value === 'NULL' || $value === NULL){

                        $insert_arr['values'] .= "NULL" . ',';

                    }else{

                        $insert_arr['values'] .= "'" . addslashes($value) . "',";
                    }

                    $j++;

                    if($j === $count_fields) break;
                }

                if($j < $count_fields)
                {
                    for(; $j < $count_fields; $j++)
                    {
                        $insert_arr['values'] .= 'NULL' . ',';
                    }
                }

                $insert_arr['values'] = rtrim($insert_arr['values'], ',') . "),";

                if(!$check_fields) $check_fields = true;

            }
        }else{

            $insert_arr['values'] = '(';

            if($fields)
            {
                foreach ($fields as $key => $value)
                {

                    if($except && in_array($key, $except)) continue;

                    $insert_arr['fields'] .= $key . ',';

                    if(in_array($value, $this->sqlFunc))
                    {
                        $insert_arr['values'] .= $value . ',';

                    }elseif($value === 'NULL' || $value === NULL){

                        $insert_arr['values'] .= "NULL" . ',';

                    }else{

                        $insert_arr['values'] .= "'" . addslashes($value) . "',";
                    }

                }
            }

            if($files)
            {

                foreach ($files as $key => $file)
                {

                    $insert_arr['fields'] .= $key . ',';

                    if(is_array($file)) $insert_arr['values'] .= "'" . addslashes(json_encode($file)) . "',";
                        else $insert_arr['values'] .= "'" . addslashes($file) . "',";
                    
                }

            }

            $insert_arr['values'] = rtrim($insert_arr['values'], ',') . ')';

        }

        $insert_arr['fields'] = rtrim($insert_arr['fields'], ',') . ')';
        $insert_arr['values'] = rtrim($insert_arr['values'], ',');

        return $insert_arr;
    }

    protected function createUpdate($fields, $files, $except)
    {

        $update = '';

        if($fields)
        {
            foreach ($fields as $row => $value)
            {
                if($except && in_array($row, $except)) continue;

                $update .= $row . '=';

                if (in_array($value, $this->sqlFunc))
                {
                    $update .= $value . ',';
                }elseif($value === NULL || $value === 'NULL') {
                    $update .= "NULL" . ',';
                }else{
                    $update .= "'" . addslashes($value) . "',";
                }

            }
        }

        if($files)
        {
            foreach ($files as $row => $file)
            {

                $update .= $row . '=';

                if(is_array($file)) $update .= "'" . addslashes(json_encode($file)) . "',";
                else $update .= "'" . addslashes($file) . "',";

            }
        }

        return rtrim($update, ',');

    }

    protected function joinStructure($res, $table)
    {

        $joinArr = [];

        $idRow = $this->tableRows[$this->createTableAlias($table)['alias']]['id_row']; // для удобства

        foreach ($res as $value)
        {
            if($value)
            {
                if(!isset($joinArr[$value[$idRow]])) $joinArr[$value[$idRow]] = [];

                foreach ($value as $key => $item)
                {
                    if(preg_match('/TABLE(.+)?TABLE/u', $key, $matches))
                    {
                        /*
                         * matches[0] => TABLE(.+)?TABLE (все вхождения этой регулярки из проверяемой ячейки)
                         * matches[1] => (.+) (здесь уже будет хранится результат поиска исключительно по регулярным символам)
                         * */
                        $tableNameNormal = $matches[1];

                        if(!isset($this->tableRows[$tableNameNormal]['multi_id_row']))
                        {
                            $joinIdRow = $value[$matches[0] . '_' . $this->tableRows[$tableNameNormal]['id_row']];
                        }else{

                            $joinIdRow = '';

                            foreach ($this->tableRows[$tableNameNormal]['multi_id_row'] as $multi)
                            {
                                $joinIdRow .= $value[$matches[0] . '_' . $multi];
                            }
                        }

                        $row = preg_replace('/TABLE(.+)TABLE_/u', '', $key);

                        if($joinIdRow && !isset($joinArr[$value[$idRow]]['join'][$tableNameNormal][$joinIdRow][$row]))
                        {
                            $joinArr[$value[$idRow]]['join'][$tableNameNormal][$joinIdRow][$row] = $item;
                        }

                        continue;
                    }

                    $joinArr[$value[$idRow]][$key] = $item;

                }
            }
        }

        return $joinArr;
    }

    // создает алиасы для наших таблиц
    protected  function createTableAlias($table)
    {

        $arr = [];

        if(preg_match('/\s+/i', $table))
        {

            // ищем лишние пробелы, если они имеются
            // '/\s{2,}/i' => пробел 2 и более раз
            $table = preg_replace('/\s{2,}/i', ' ', $table);

            $tableName = explode(' ', $table);

            $arr['table'] = trim($tableName[0]);
            $arr['alias'] = trim($tableName[1]);
        }else{

            $arr['alias'] = $arr['table'] = $table;

        }

        return $arr;

    }
}