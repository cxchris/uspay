<?php

namespace app\admin\validate;

use think\Validate;

class Otc extends Validate
{

    /**
     * 验证规则
     */
    // |unique:channel_list
    protected $rule = [
        'account_name' => 'require|max:30',
        'account_number' => 'require|max:30',
        'ifsc' => 'require|max:20',
        'day_limit' => 'require|between:0,100000000',
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
        'add'  => ['account_name', 'account_number', 'ifsc', 'day_limit'],
        'edit' => ['account_name', 'account_number', 'ifsc', 'day_limit'],
    ];

    public function __construct(array $rules = [], $message = [], $field = [])
    {
        $this->field = [
            'account_name' => '户主',
            'account_number' => '账号',
            'ifsc' => 'ifsc',
            'day_limit' => '每日限额',
        ];
        $this->message = array_merge($this->message, [
            'account_name.regex' => '户主只能由3-30位数字、字母、下划线组合',
            'account_number.regex' => '账号只能由3-30位数字、字母、下划线组合',
            'ifsc.regex' => 'ifsc只能由3-30位数字、字母、下划线组合',
        ]);
        parent::__construct($rules, $message, $field);
    }

}
