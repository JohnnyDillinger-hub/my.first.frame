<?php


namespace libraries;


class TextModify
{

    protected $translitArr = [ 'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e',
                                'ё' => 'yo', 'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k',
                                'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r',
                                'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'ts',
                                'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => 'y', 'ы' => 'y',
                                'ь' => 'y', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya', ' ' => '-',
    ];

    // массив букв которые будет смягчать мягкий знак
    protected $lowelLetter = ['а', 'е', 'и', 'о', 'у', 'э'];

    // метод для перевода имени файла по методу транслита
    public function translit($str){

        //mb_strtolower($str) - приводит строку к нижнему регистру
        $str = mb_strtolower($str);

        $tempArr = [];

        // раскладываем название файла на отдельнве символы
        for($index = 0; $index < mb_strlen($str); ++$index) {

            $temp_arr[] = mb_substr($str, $index, 1);
        }

        $link = '';

        if($temp_arr){
            foreach ($temp_arr as $key => $char) {

                if(array_key_exists($char, $this->translitArr)){

                    // ищем сложные буквы по кириллице
                    switch ($char){

                        case 'ъ':
                            if($temp_arr[$key + 1] == 'е') $link .= 'y';
                            break;

                        case 'ы':
                            if($temp_arr[$key + 1] == 'й') $link .= 'i';
                                else $link .= $this->translitArr[$char];
                            break;

                        case 'ь':
                            if($temp_arr[$key+1] !== count($temp_arr) && in_array($temp_arr[$key + 1], $this->lowelLetter)){
                                $link .= $this->translitArr[$char];
                            }
                            break;
                        // если итерационный символ не является сложной буквой по кириллице, то просто делаем транслитерацию
                        default:
                            $link .= $this->translitArr[$char];
                            break;
                    }

                }else{
                    $link .= $char;
                }

            }
        }
        
        if($link){

            // обрабатываем ссылку и удаляем ненужные символы
            $link = preg_replace('/[^a-z0-9_-]/iu', '', $link);
            $link = preg_replace('/-{2,}]/iu', '-', $link);
            $link = preg_replace('/_{2,}]/iu', '_', $link);
            $link = preg_replace('/(^[-_]+)|([-_]+$)/iu', '', $link);

        }

        return $link;

    }

}