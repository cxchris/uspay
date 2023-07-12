<?php

namespace app\admin\model;

use think\Model;
use think\Session;

class SystemAmountChangeRecord extends Model
{
    protected $name = 'system_amount_change_record';
    
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $create_time = 'create_time';

}
