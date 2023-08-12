<?php

namespace app\index\controller;

use app\common\controller\Frontend;
use think\Db;
use think\Log;

class Expire extends Frontend
{

    protected $noNeedLogin = '*';
    protected $noNeedRight = '*';
    const msgList = [
        [
            'id' => 1,
            'title' => 'order has expired',
            'msg' => 'Please order and pay again',
        ],
    ];

    public function _initialize(){
    }

    public function index()
    {
        $id = $this->request->param('id');

        $msg = self::msgList[0]; // 初始化变量用于存储匹配到的消息
        
        // 遍历 msgList 数组，查找匹配的 id 值的消息
        foreach (self::msgList as $item) {
            if ($item['id'] == $id) {
                $msg = $item;
                break; // 找到匹配的消息后跳出循环
            }
        }


        $this->view->assign('msg', $msg);
        return $this->view->fetch();
    }
}
