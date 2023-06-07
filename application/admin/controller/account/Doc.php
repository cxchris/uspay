<?php

namespace app\admin\controller\account;

use app\common\controller\Backend;
use think\Db;
use think\Validate;
use fast\Sign;
use fast\Http;
use think\Log;
use app\admin\library\Paytm;

/**
 * 开发者信息
 *
 * @icon   fa fa-circle-o
 * @remark 主要用于展示商户账户信息
 */
class Doc extends Backend
{

    /**
     * @var \app\common\model\Attachment
     */
    protected $model = null;

    protected $type = 1; //代收-1，代付-2
    protected $noNeedRight = ['*'];
    protected $url = 'https://ydapppay.com'; //443地址
    protected $httpurl = 'http://ydapppay.com'; //
    protected $ds_url = '/paylink/doc/pay.html'; //代收文档地址
    protected $df_url = '/paylink/doc/apply.html'; //代付文档地址

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('pay_order');

        if (!in_array($this->group_id , [self::MERCHANT_GROUP])) {
            $this->error('商户才可以查看');
        }
    }

    /**
     * 查看
     */
    public function index()
    {
        $row = $this->merchant;
        if (!$row) {
            $this->error(__('No Results were found'));
        }

        $row['url'] = $this->url;
        $row['ds_url'] = $this->httpurl.$this->ds_url;
        $row['df_url'] = $this->httpurl.$this->df_url;

        $this->view->assign("row", $row);

        if($this->group_id != self::MERCHANT_GROUP){
            echo '商户才可以查看';
        }else{
            //商户组
            return $this->view->fetch();
        }
    }
    
}
