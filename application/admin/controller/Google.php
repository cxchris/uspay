<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use think\Config;
use think\Hook;
use think\Session;
use think\Validate;

/**
 * 谷歌验证
 * @internal
 */
class Google extends Backend
{
    protected $id;
    protected $row;
    protected $noNeedRight = ['*'];
    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('Admin');

    }

    /**
     * 获取
     */
    public function get($ids = null)
    {
        $params = $_REQUEST;
        if(!$params || !$params['id'] || !$params['Checksum']){
            $this->error('参数不能为空',null,[]);
        }
        $row = $this->model->get(['id' => $params['id']]);
        if(!$row){
            $this->error('用户不存在',null,[]);
        }

        //先验证后台操作员自己的谷歌验证码是否正确
        if(!$this->checkValid($params['Checksum'])){
            $this->error('谷歌校验码错误',null,[]);
        }

        //判断商户的谷歌验证码是否存在，如果存在，则返回，不存在，则生成新的
        if($row->checkSum == ''){
            $secret = $this->ga->createSecret();
            //生成后保存到数据库
            $row->checkSum = $secret;
            $row->save();
        }else{
            $secret = $row->checkSum;
        }

        
        $data = ['Checksum' => $secret];
        $this->success('成功',null,$data);
    }

    /**
     * 重置
     */
    public function reset($ids = null)
    {
        $params = $_REQUEST;
        if(!$params || !$params['id'] || !$params['Checksum']){
            $this->error('参数不能为空',null,[]);
        }
        $row = $this->model->get(['id' => $params['id']]);
        if(!$row){
            $this->error('用户不存在',null,[]);
        }

        //先验证后台操作员自己的谷歌验证码是否正确
        if(!$this->checkValid($params['Checksum'])){
            $this->error('谷歌校验码错误',null,[]);
        }

        $secret = $this->ga->createSecret();
        //生成后保存到数据库
        $row->checkSum = $secret;
        $row->save();
        
        $data = ['Checksum' => $secret];
        $this->success('成功',null,$data);
    }

    
}
