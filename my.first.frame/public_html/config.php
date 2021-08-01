<?php

defined("VG_ACCESS") or die("Access denied"); /* функция проверки наличия константы определённой через функция
                                                     define()*/

const SITE_URL = "http://cpa.fvds.ru"; // хранит ссылку на наш сайт
const PATH = "/";                // хранит корень пути нашего сайта

const HOST = "localhost";        // хост для подключения к базе данных
const USER = "root";             // логин для входа в базу данных
const PASS = "root";                 // пароль от базы данных (по умолчанию он отсутствует)
const DB_NAME = "test.db.frame";    // имя базы данных

