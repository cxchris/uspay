<?php

namespace app\admin\model;

use think\Model;
use think\Session;

class AmountOrderLog extends Model
{
    protected $name = 'amount_order_log';
    
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $create_time = 'create_time';
    protected $update_time = 'update_time';

}
