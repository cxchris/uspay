<?php
namespace app\admin\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Log;
use think\Db;
use app\common\model\PaymentOrder;

/*
* 查询payg订单状态并更新
*/
class Payoutsearch extends Command
{
    public static $debug = 0;
    public static $sync_sec = 60;//回写数据库时间
    protected function configure(){
        $this->setName('Payoutsearch')->setDescription("计划任务 Payoutsearch");
    }

    protected function execute(Input $input, Output $output){
        $output->writeln('Payoutsearch Crontab job start...');
        /*** 这里写计划任务列表集 START ***/

        $this->domain();              // 调用方法

        /*** 这里写计划任务列表集 END ***/
        $output->writeln('Payoutsearch Crontab job end...');
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
        // Log::record('Payoutsearch定时任务开启');
        $this->output->writeln('Payoutsearch running...');

        $model = new PaymentOrder();
        //查询订单
        $record = Db::name('payment_order')
            ->alias('a')
            ->field('a.*,b.merchant_key,c.channel_type,b.merchant_number')
            ->join('merchant b','a.merchant_id = b.id','LEFT')
            ->join('channel_list c','a.channel_id = c.id','LEFT')
            ->where('a.status','in','0,1')
            ->where('pay_type','payg')
            // ->limit(100)
            ->select();
        // echo Db::name('payment_order')->getLastsql();exit;
        // dump($record);exit;
        foreach ($record as $k => $row) {
            // $channel = Db::name('channel_list')->where(array('id'=>$row['channel_id']))->find();
            
            //查询三方状态
            $status = $model->get_order_detail($row);
        }
        
    }
}
