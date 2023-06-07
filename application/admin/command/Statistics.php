<?php
namespace app\admin\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Log;
use think\Db;
use app\common\model\PayOrder;

/*
* 商户每日数据定时
*/
class Statistics extends Command
{
    public static $debug = 0;
    public static $sync_sec = 60;//回写数据库时间

    protected function configure(){
        $this->setName('Statistics')->setDescription("计划任务 Statistics");
    }

    protected function execute(Input $input, Output $output){
        $this->output = $output;
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
        // Log::record('Statistics定时任务开启');
        $this->output->writeln('Statistics running...');
        //看今天有没有记录昨天的数据，记录就跳过，没有就跑一次昨天的数据
        $current = time();
        $d = 1;
        $datetime = strtotime("-".$d." day"); //昨天的时间戳

        $is_record = Db::name('sign')->where(['name'=>__CLASS__,'datetime'=>date('Y-m-d',$datetime)])->find();


        if(!$is_record){
            //没有昨天的记录，跑昨天的数据，同时记录is_record
            $this->addrecord($datetime);

            //添加记录
            Db::name('sign')->insert(['name'=>__CLASS__ ,'datetime'=>date('Y-m-d',$datetime)]);
        }

        //跑今天的记录
        $this->addrecord($current);
        
    }


    //记录统计
    private function addrecord($time){
        $model = new PayOrder();
        //获取商户列表
        $merchant = Db::name('merchant')->field('id,merchant_number')->where('status',1)->select();

        //今天的日期
        $datetime = date('Y-m-d',$time);
        $starttime = date('Y-m-d 00:00:00',$time);
        $endtime = date('Y-m-d 23:59:59',$time);

        if($merchant){
            foreach ($merchant as $k => $v) {

                //查询数据
                $todaydata = $model->getpaydata($v['id'],$v['merchant_number'],$starttime,$endtime,$datetime);

                //插入数据
                $this->dailydata($v['id'],$datetime,$todaydata);
            }
        }

        //跑完商户数据，跑系统所有数据

        //查询数据
        $todaydata = $model->getpaydata(0,'',$starttime,$endtime,$datetime);

        //插入数据
        $this->dailydata(0,$datetime,$todaydata);
    }

    //日出数据处理
    private function dailydata($merchant_id,$datetime,$data){
        $model = Db::name('merchant_daily');
        //先查找有无记录
        $record = $model->where(['merchant_id'=>$merchant_id,'datetime' => $datetime])->find();
        if($record){
            $ret = $model->where(['merchant_id'=>$merchant_id,'datetime' => $datetime])->update($data);
        }else{
            $ret = $model->insert($data);
        }
        return $ret;
    }
}
