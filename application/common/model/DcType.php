<?php

namespace app\common\model;

use think\Model;
use think\Session;
use think\Db;
use fast\Sign;
use fast\Http;
use think\Log;


class DcType extends Model
{
    protected $name = 'dc_list';
    
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $create_time = null;
    protected $updateTime = null;
    protected $notFoundFields = ['create_time', 'update_time'];
}
