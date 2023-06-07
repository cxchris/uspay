<?php

namespace app\admin\validate;

use think\Validate;

class Channel extends Validate
{
     /**
     * 验证规则
     */
    // |unique:channel_list
    protected $rule = [
        'merchantNo' => 'require|max:64',
        'type' => 'require|max:2',
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
        // 'orderInfo'  => ['merchantNo','merchantSn','sign','time'],
    ];

    public function __construct(array $rules = [], $message = [], $field = [])
    {
        $this->field = [
            'merchantNo' => '商户号',
            'type' => '通道类型',
            'sign' => '签名',
        ];
        $this->message = array_merge($this->message, [
        ]);
        parent::__construct($rules, $message, $field);
    }

}
