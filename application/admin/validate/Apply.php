<?php

namespace app\admin\validate;

use think\Validate;

class Apply extends Validate
{
     /**
     * 验证规则
     */
    // |unique:channel_list
    protected $rule = [
        'merchantNo' => 'require|max:64',
        'merchantSn' => 'require|max:64',
        'amount' => 'require|between:1,100000',
        // 'channel' => 'require',
        'notifyUrl' => 'require',
        // 'callbackUrl' => 'require',

        'accountName' => 'require|max:128',
        'accountNo' => 'require|max:128',
        'bankName' => 'require|max:128',
        'bankCode' => 'require|max:128',
        // 'branchName' => 'require|max:128',

        'time' => 'require|integer|max:20',
        'sign' => 'require|max:128',
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
        'orderInfo'  => ['merchantNo','merchantSn','sign','time'],
    ];

    public function __construct(array $rules = [], $message = [], $field = [])
    {
        $this->field = [
            'merchantNo' => '商户号',
            'merchantSn' => '商户订单号',
            'amount' => '支付金额',
            'channel' => '支付通道',
            'notifyUrl' => '回调地址',
            // 'callbackUrl' => '跳转地址',

            'accountName' => '收款人账户名',
            'accountNo' => '收款人账户号',
            'bankName' => '开户行名称',
            'bankCode' => '银行编号',
            
            'time' => '时间戳',
            'sign' => '签名',
        ];
        $this->message = array_merge($this->message, [
        ]);
        parent::__construct($rules, $message, $field);
    }

}
