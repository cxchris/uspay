<?php

namespace app\admin\controller;

use app\admin\model\Admin;
use app\admin\model\User;
use app\common\controller\Backend;
use app\common\model\Attachment;
use fast\Date;
use think\Db;

/**
 * 控制台
 *
 * @icon   fa fa-dashboard
 * @remark 用于展示当前系统中的统计数据、统计报表及重要实时数据
 */
class Statistics extends Backend
{

    // 无需鉴权的接口,*表示全部
    protected $noNeedRight = ['*'];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('pay_order');
    }
    /**
     * 查看
     */
    public function index()
    {
        // if($this->group_id != self::MERCHANT_GROUP){
        //     echo '商户才可以查看';exit;
        // }

        //查询近15天内商户的成功金额、成功订单数
        $list = $this->getpaylist();
        $moneylist = $list['money'];
        $totallist = $list['total'];

        //获取今日代收总订单金额（总订单数），成功订单金额（成功订单数）计算百分比
        $today_success_money = end($moneylist);
        $today_success_total = end($totallist);

        $res = $this->getpayrecord();
        $today_total_money = $res[0]['nums']??0;
        $today_total_total = $res[0]['counts']??0;

        $today_money_rate = $this->getrate($today_success_money,$today_total_money);
        $today_total_rate = $this->getrate($today_success_total,$today_total_total);

        //获取总金额（总订单数），成功订单金额（成功订单数）计算百分比
        $ret = $this->gettotalpayrecord(0);
        $total_money = $ret[0]['nums']??0;
        $total_total = $ret[0]['counts']??0;

        $success_ret = $this->gettotalpayrecord(1);
        $success_money = $success_ret[0]['nums']??0;
        $success_total = $success_ret[0]['counts']??0;

        $money_rate = $this->getrate($success_money,$total_money);
        $total_rate = $this->getrate($success_total,$total_total);

        $this->view->assign([
            'today_success_money' => sprintfnum($today_success_money), //今日成功总金额
            'today_success_total' => $today_success_total, //今日成功总订单数
            'today_total_money' => sprintfnum($today_total_money), //今日总金额
            'today_total_total' => $today_total_total, //今日总订单数
            'today_money_rate' => $today_money_rate, //今日成功金额占比
            'today_total_rate' => $today_total_rate, //今日订单成功率


            'success_money' => sprintfnum($success_money), //成功总金额
            'success_total' => $success_total, //成功总订单数
            'total_money' => sprintfnum($total_money), //总金额
            'total_total' => $total_total, //总订单数
            'money_rate' => $money_rate, //成功金额占比
            'total_rate' => $total_rate, //订单成功率
        ]);

        $this->assignconfig('money_column', array_keys($moneylist));
        $this->assignconfig('money_data', array_values($moneylist));

        $this->assignconfig('total_column', array_keys($totallist));
        $this->assignconfig('total_data', array_values($totallist));


        return $this->view->fetch();
    }

    /*
    * 公共方法，获取占比
    */
    protected function getrate($money,$total){
        if($total != 0){
            $rate = (sprintf("%.2f",($money/$total))*100).'%';
        }else{
            $rate = '0%';
        }

        return $rate;
    }

    /*
    * 公共方法，获取折线图记录
    */
    protected function getpaylist(){

        $field = 'COUNT(*) AS counts, sum(money) AS nums';
        $where = ['status'=>1];

        if($this->group_id != self::MERCHANT_GROUP){
        }else{
            $where['merchant_number'] = $this->merchant['merchant_number'];
        }

        $column = [];
        $starttime = Date::unixtime('day', -14);
        $endtime = Date::unixtime('day', 0, 'end');
        for ($time = $starttime; $time <= $endtime;) {
            $column[] = date("Y-m-d", $time);
            $time += 86400;
        }
        $time_field = 'create_time';
        $group_create_time = 'group_create_time';

        //查询近15天内商户的成功金额
        $paylist = Db("pay_order")
            ->where($time_field, 'between time', [$starttime, $endtime])
            ->where($where)
            ->field( $field.', DATE_FORMAT(FROM_UNIXTIME('.$time_field.'), "%Y-%m-%d") AS '.$group_create_time)
            ->group($group_create_time)
            ->select();
            // echo Db("pay_order")->getlastsql();exit;

        $total = array_fill_keys($column, 0);
        $money = array_fill_keys($column, 0);
        foreach ($paylist as $k => $v) {
            
            $total[$v[$group_create_time]] = $v['counts'];
            $money[$v[$group_create_time]] = $v['nums'];
        }

        $result = [
            'total' => $total,
            'money' => $money
        ];
        return $result;
    }

    /*
    * 公共方法，获取pay_order
    */
    protected function getpayrecord(){
        $field = 'COUNT(*) AS counts, sum(money) AS nums';

        $starttime = Date::unixtime('day', 0);
        $endtime = Date::unixtime('day', 0, 'end');

        if($this->group_id != self::MERCHANT_GROUP){
            $where = [];
        }else{
            $where['merchant_number'] = $this->merchant['merchant_number'];
        }

        $time_field = 'create_time';
        $res = Db("pay_order")
            ->where($time_field, 'between time', [$starttime, $endtime])
            ->where($where)
            ->field($field)
            ->select();
            // echo Db("pay_order")->getlastsql();exit;

        return $res;
    }

    /*
    * 公共方法，获取pay_order
    */
    protected function gettotalpayrecord($status = 1){
        $field = 'COUNT(*) AS counts, sum(money) AS nums';

        if($this->group_id != self::MERCHANT_GROUP){
            $where = [];
        }else{
            $where['merchant_number'] = $this->merchant['merchant_number'];
        }

        if($status == 1){
            $where['status'] = $status;
        }

        $res = Db("pay_order")
            ->where($where)
            ->field($field)
            ->select();
            // echo Db("pay_order")->getlastsql();exit;

        return $res;
    }


}
