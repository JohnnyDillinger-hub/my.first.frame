<?php


namespace libraries;


class FileEdit
{

    protected $imgArr = [];
    protected $directory;

    public function addFile($directory = false)
    {

        // если директория не указана заранее, то добавляем её
        if(!$directory) $this->directory = $_SERVER['DOCUMENT_ROOT'] . PATH . UPLOAD_DIR;
            else $this->directory = $directory;

        foreach ($_FILES as $key => $file)
        {
            if(is_array($file['name'])){

                $file_arr = [];

                foreach($file['name'] as $index => $value){

                    if(!empty($file['name'][$index]))
                    {
                        $file_arr['name'] = $file['name'][$index];
                        $file_arr['type'] = $file['type'][$index];
                        $file_arr['tmp_name'] = $file['tmp_name'][$index];
                        $file_arr['error'] = $file['error'][$index];
                        $file_arr['size'] = $file['size'][$index];

                        $res_name = $this->createFile($file_arr);

                        if($res_name) $this->imgArr[$key][] = $res_name;
                    }

                }

            }else{

                if($file['name']){

                    $res_name = $this->createFile($file);

                    if($res_name) $this->imgArr[$key] = $res_name;

                }
            }
        }

        return $this->getFiles();

    }

    protected function createFile($file)
    {

        // делим название файла, чтобы получить отдельного его формат и его наименование
        $fileNameArr = explode('.', $file['name']);
        //получаем расширения файла
        $ext = $fileNameArr[count($fileNameArr) - 1];

        unset($fileNameArr[count($fileNameArr) - 1]);

        $fileName = implode('.', $fileNameArr);

        $fileName = (new TextModify())->translit($fileName);

        $fileName = $this->checkFile($fileName, $ext);

        // полный путь к файлу
        $fileFullName = $this->directory . $fileName;

        if($this->uploadFile($file['tmp_name'], $fileFullName))
            return $fileName;

        return false;
    }

    protected function uploadFile($tmpName, $dest)
    {

        // move_uploaded_file — Перемещает загруженный файл в новое место
        if(move_uploaded_file($tmpName, $dest)) return true;

        return false;

    }

    // проверяем наличие файла в системе
    protected function checkFile($fileName, $ext, $fileLastName = '')
    {

        if(!file_exists($this->directory . $fileName . $fileLastName . '.' . $ext))
        {
            return $fileName . $fileLastName . '.' . $ext;
        }

        return $this->checkFile($fileName, $ext,'_' . hash('crc32', time() . mt_rand(0, 100)));
    }

    public function getFiles()
    {
        return $this->imgArr;
    }

}