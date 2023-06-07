<?php

namespace app\admin\validate;

use think\Validate;

class MerchantBank extends Validate
{

    /**
     * 验证规则
     */
    // |unique:channel_list
    protected $rule = [
        'account' => 'require',
        'bankname' => 'require',
        'banknumber' => 'require',
        'ifsccode' => 'require',
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
        'add'  => ['account', 'bankname', 'banknumber', 'ifsccode'],
        'edit' => ['account', 'bankname', 'banknumber', 'ifsccode'],
    ];

    public function __construct(array $rules = [], $message = [], $field = [])
    {
        $this->field = [
            'account' => '账户名',
            'bankname' => '银行名称',
            'banknumber' => '银行账户',
            'ifsccode' => 'IFSC Code',
        ];
        $this->message = array_merge($this->message, [
        ]);
        parent::__construct($rules, $message, $field);
    }

}
