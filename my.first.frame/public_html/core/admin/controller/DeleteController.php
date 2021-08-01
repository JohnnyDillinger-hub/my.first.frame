<?php


namespace core\admin\controller;


use core\base\settings\Settings;

class DeleteController extends BaseAdmin
{

    protected function inputData()
    {

        if(!$this->userId) $this->execBase();

        $this->createTableData();

        if(!empty($this->parameters[$this->table]))
        {

            $id = is_numeric($_POST[$this->columns['id_row']]) ?
                $this->clearNum($this->parameters[$this->table]) :
                $this->clearStr($this->parameters[$this->table]);

            if($id)
            {

                $this->data = $this->model->get($this->table, [
                    'where' => [$this->columns['id_row'] => $id]
                ]);

                if($this->data)
                {

                    $this->data = $this->data[0];

                    if(count($this->parameters) > 1)
                    {

                        $this->checkDeleteFile();

                    }

                    $settings = $this->settings ?: Settings::instance();

                    $files = $settings::get('fileTemplates');

                    if($files)
                    {

                        foreach ($files as $file)
                        {

                            foreach ($settings::get('templateArr')[$file] as $item)
                            {
                                if(!empty($this->data[$item]))
                                {

                                    $fileData = json_decode($this->data[$item], true) ?: $this->data[$item];

//                                    if(preg_match('/^[\[\{].*?[\}\]]$/', $this->data[$item]))
//                                        $fileData = json_decode($this->data[$item], true);
//                                    else
//                                        $fileData = $this->data[$item];

                                    if(is_array($fileData))
                                    {
                                        foreach ($fileData as $f)
                                        {
                                            @unlink($_SESSION['DOCUMENT_ROOT'] . PATH . UPLOAD_DIR  . $f);
                                        }

                                    }else{

                                        @unlink($_SESSION['DOCUMENT_ROOT'] . PATH . UPLOAD_DIR  . $fileData);

                                    }

                                }

                            }

                        }

                    }
                    
                    if(!empty($this->data['menu_position']))
                    {
                        $where = [];

                        if(!empty($this->data['parent_id']))
                        {

                            $pos = $this->model->get($this->table, [
                                'fields'     => ['COUNT(*) as count'],
                                'where'      => ['parent_id' => $this->data['parent_id']],
                                'no_concat'  => true
                            ])[0]['count'];

                            $where = ['where' => 'parent_id'];

                        }else{

                            $pos = $this->model->get($this->table, [
                                'fields'     => ['COUNT(*) as count'],
                                'no_concat'  => true
                            ])[0]['count'];

                        }

                        // перед удалением элемента, ставим его на самую последнюю позицию в таблице
                        $this->model->updateMenuPositionAdmin($this->table, 'menu_position',
                            [$this->columns['id_row'] => $id], $pos, $where);

                    }

                    if($this->model->delete($this->table, ['where' => [$this->columns['id_row'] => $id]]))
                    {

                        $tables = $this->model->showTables();

                        if(in_array('old_alias', $tables))
                        {
                            $this->model->delete('old_alias', [
                                'where' => [
                                    'table_name' => $this->table,
                                    'table_id'   => $id
                                ]
                            ]);
                        }

                        $manyToMany = $settings::get('manyToMany');

                        if($manyToMany)
                        {
                            foreach ($settings::get('manyToMany') as $mTable => $m_tables)
                            {

                                $targetKey = array_search($this->table, $m_tables);

                                if($targetKey !== false)
                                {

                                    $this->model->delete($mTable, [
                                        'where' => [$m_tables[$targetKey] . '_' . $this->columns['id_row'] => $id]
                                    ]);

                                }

                            }

                        }

                        $_SESSION['res']['answer'] = '<div class="success">'. $this->messages['deleteSuccess'] .'</div>';

                        $this->redirect($this->adminPath . 'show/' . $this->table);

                    }

                }

            }

        }

        $_SESSION['res']['answer'] = '<div class="error">'. $this->messages['deleteFail'] .'</div>';

        $this->redirect();

    }

    // проверяем на наличие в массиве файла для удаления
    protected function checkDeleteFile()
    {

        // удаляем название таблицы из массива parameters
        unset($this->parameters[$this->table]);

        $updateFlag = false;

        foreach ($this->parameters as $row => $item)
        {

            $item = base64_decode($item);

            // $this->data - тут хранится ячейка таблицы, в которой будет произведены изменения
            if(!empty($this->data[$row]))
            {

                $data = json_decode($this->data[$row]);

                if(is_array($data))
                {

                    foreach ($data as $key => $value)
                    {

                        if($item === $value)
                        {

                            $updateFlag = true;

                            @unlink($_SERVER['DOCUMENT_ROOT'] . PATH . UPLOAD_DIR . $item);

                            unset($data[$key]);

                            $this->data[$row] = $data ? json_encode($data) : 'NULL';

                            break;

                        }

                    }

                }elseif($this->data[$row] === $item){

                    $updateFlag = true;

                    @unlink($_SERVER['DOCUMENT_ROOT'] . PATH . UPLOAD_DIR . $item);

                    $this->data[$row] = 'NULL';

                }

            }

        }

        if($updateFlag)
        {
            $this->model->edit($this->table, [
                'fields' => $this->data
            ]);

            $_SESSION['res']['answer'] = '<div class="success">' . $this->messages['editSuccess'] . '</div>';

        }else{

            $_SESSION['res']['answer'] = '<div class="error">' . $this->messages['editFail'] . '</div>';

        }



        $this->redirect();
    }

}