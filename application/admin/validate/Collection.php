<?php

namespace app\admin\validate;

use think\Validate;

class Collection extends Validate
{

    /**
     * 验证规则
     */
    // |unique:channel_list
    protected $rule = [
        'channel_name' => 'require|max:64',
        'channel_en_name' => 'require|max:64',
        'channel_sign' => 'require|max:64',
        'low_money' => 'require|between:0,5000',
        'high_money' => 'require|between:0,100000',
        'day_limit_money' => 'require|between:0,100000000',
    ];

    /**
     * 提示消息
     */
    protected $message = [
    ];

    /**
     * 字段描述
     */
    protected $field = [
    ];

    /**
     * 验证场景
     */
    protected $scene = [
        'add'  => ['channel_name', 'channel_en_name', 'channel_sign', 'low_money', 'high_money', 'day_limit_money'],
        'edit' => ['channel_name', 'channel_en_name', 'channel_sign', 'low_money', 'high_money', 'day_limit_money'],
    ];

    public function __construct(array $rules = [], $message = [], $field = [])
    {
        $this->field = [
            'channel_name' => '通道名称',
            'channel_en_name' => '英文名称',
            'channel_sign' => '通道商户号',
            'low_money' => '最低金额',
            'high_money' => '最高金额',
            'day_limit_money' => '每日限额',
        ];
        $this->message = array_merge($this->message, [
            'channel_name.regex' => '通道名称只能由3-30位数字、字母、下划线组合',
            'channel_en_name.regex' => '英文名称只能由3-30位数字、字母、下划线组合',
            'channel_sign.regex' => '通道商户号只能由3-30位数字、字母、下划线组合',
        ]);
        parent::__construct($rules, $message, $field);
    }

}
