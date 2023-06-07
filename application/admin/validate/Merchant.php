<?php

namespace app\admin\validate;

use think\Validate;

class Merchant extends Validate
{

    /**
     * 验证规则
     */
    // |unique:channel_list
    protected $rule = [
        'merchant_name' => 'require',
        'collection_limit' => 'require|between:0,100000000',
        'payment_limit' => 'require|between:0,100000000',
        'collection_low_money' => 'require|between:0,1000',
        'payment_low_money' => 'require|between:0,1000',
        'collection_high_money' => 'require|between:0,50000',
        'payment_high_money' => 'require|between:0,50000',
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
        'add'  => ['merchant_name', 'collection_limit', 'payment_limit', 'collection_low_money', 'payment_low_money', 'collection_high_money', 'payment_high_money'],
        'edit' => ['merchant_name', 'collection_limit', 'payment_limit', 'collection_low_money', 'payment_low_money', 'collection_high_money', 'payment_high_money'],
    ];

    public function __construct(array $rules = [], $message = [], $field = [])
    {
        $this->field = [
            'merchant_name' => '商户名称',
            'collection_limit' => '每日代收限额',
            'payment_limit' => '每日代付限额',
            'collection_low_money' => '代收最低金额',
            'payment_low_money' => '代付最低金额',
            'collection_high_money' => '代收最高金额',
            'payment_high_money' => '代付最高金额',
        ];
        $this->message = array_merge($this->message, [
            'merchant_name.regex' => '商户名称只能由3-30位数字、字母、下划线组合',
        ]);
        parent::__construct($rules, $message, $field);
    }

}
