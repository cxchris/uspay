<?php
namespace app\admin\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Log;
use think\Db;
use app\common\model\PayOrder;
use app\admin\library\Paytm;

/*
* 商户手动每日结算
*/
class Checktest extends Command
{
    public static $debug = 1;
    public static $sync_sec = 90;//回写数据库时间
    protected function configure(){
        $this->setName('Checktest')->setDescription("计划任务 Checktest");
    }

    protected function execute(Input $input, Output $output){
        $output->writeln('Date Crontab job start...');
        /*** 这里写计划任务列表集 START ***/

        $this->domain();              // 调用方法

        /*** 这里写计划任务列表集 END ***/
        $output->writeln('Date Crontab job end...');
    }

    public function domain(){
        if(self::$debug == 0){
            while(1) { 
                $this->sync();
                sleep(self::$sync_sec);
            }
        }else{
            $this->sync();
        }
    }

    private function sync(){         // 逻辑
        // Log::record('Checktest定时任务开启');
        $this->output->writeln('Checktest running...');

        //看今天有没有记录昨天的数据，记录就跳过，没有就跑一次昨天的数据
        $current = time();
        $d = 1;
        $datetime = strtotime("-".$d." day"); //昨天的时间戳
        $is_record = Db::name('sign')->where(['name'=>__CLASS__,'datetime'=>date('Y-m-d',$datetime)])->find();

        //跑过了，就不执行任务
        // if(!$is_record && date('H',$current) > 8){
            $this->output->writeln('Checktest run');
            //没有昨天的记录，跑昨天的数据，同时记录is_record
            $res = $this->checkprocess($datetime);

            // if($res != false){
            //     //添加记录
            //     Db::name('sign')->insert(['name'=>__CLASS__ ,'datetime'=>date('Y-m-d',$datetime)]);
            // }
        // }
        
    }


    //记录统计
    private function checkprocess($time){
        $starttime = date('Y-m-d',$time);
        $endtime = date('Y-m-d',$time + 86400);
        // dump($endtime);exit;
        $model = new PayOrder();

        //获取paytm类型的通道
        // $channel = Db::name('channel_list')->where(['channel_type'=>'Payment','status'=>1,'type'=>1])->select();
        $channel = Db::name('channel_list')->where(['id'=>10])->select();
        // dump($channel);exit;
        //请求paytm
        foreach ($channel as $k => $v) {
            $res = Paytm::settlement($starttime,$endtime,$v,1,100);
            dump($res);exit;
            if($res['code'] != '0000'){
                //跳过
                Log::record('Check查询失败：'.json_encode($res));
                return false;
            }else{
                $totalpage = $res['data']['paginatorTotalPage'];
                if($totalpage > 0){
                    //批量结算
                    for ($i=1; $i <= $totalpage; $i++) { 
                        $paytmret = Paytm::settlement($starttime,$endtime,$v,$i,100);
                        if($paytmret['code'] != '0000'){
                            Log::record('Check查询失败：'.json_encode($paytmret));
                            return false;
                        }else{
                            $list = $paytmret['data']['settlementTransactionList'];
                            // dump($list);exit;

                            Log::record('Check第'.$i.'页');
                            if($list){
                                foreach ($list as $key => $value) {
                                    //状态为结算状态的才去结算
                                    if($value['TXNTYPE'] == 'TRANSACTION'){
                                        //查询订单
                                        $record = Db::name('pay_order')
                                                    ->where('eshopno',$value['ORDERID'])
                                                    ->where('is_billing',0)
                                                    ->where('status',1)
                                                    ->find();
                                        if($record){
                                            //税款
                                            $GST = isset($value['GST'])?$value['GST']:0;
                                            $COMMISSION = isset($value['COMMISSION'])?$value['COMMISSION']:0;
                                            $rate_t_money = $GST + $COMMISSION;

                                            $record['rate_t_money'] = $rate_t_money;
                                            $model->check_pay_order($record);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }else{
                    return true;
                }
            }
        }

        return true;
    }
}
