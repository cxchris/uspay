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
* 查询paytm订单状态并更新
*/
class Search extends Command
{
    public static $debug = 0;
    public static $sync_sec = 30;//回写数据库时间
    protected function configure(){
        $this->setName('Search')->setDescription("计划任务 Search");
    }

    protected function execute(Input $input, Output $output){
        $output->writeln('Search Crontab job start...');
        /*** 这里写计划任务列表集 START ***/

        $this->domain();              // 调用方法

        /*** 这里写计划任务列表集 END ***/
        $output->writeln('Search Crontab job end...');
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
        // Log::record('Search定时任务开启');
        $this->output->writeln('Search running...');

        $model = new PayOrder();
        //查询订单
        $record = Db::name('pay_order')
            ->alias('a')
            ->field('a.*,b.merchant_key')
            ->join('merchant b','a.merchant_number = b.merchant_number','LEFT')
            ->where(['a.status'=>[ [ 'eq' , 0] ,  [ 'eq' , 3 ] , 'or' ]])
            // ->where('pay_type','Payment')
            // ->limit(100)
            ->select();
        // echo Db::name('pay_order')->getLastsql();exit;
        // dump($record);exit;
        foreach ($record as $k => $row) {
            $channel = Db::name('channel_list')->where(array('id'=>$row['channel_id']))->find();
            
            //查询三方状态
            $status = $model->get_order_detail($row,$channel);
        }
        
    }
}
