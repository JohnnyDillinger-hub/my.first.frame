<?php


namespace core\admin\controller;


use core\base\controller\BaseController;
// use core\base\models\BaseModel;
use core\admin\models\Model;
use core\base\settings\Settings;

class IndexController extends BaseController
{

    protected function inputData()
    {

        $redirect = PATH . Settings::get('routes')['admin']['alias'] . '/show';
        $this->redirect($redirect);

    }

}