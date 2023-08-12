<?php
namespace app\admin\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Log;
use think\Db;
use fast\Http;
use app\common\model\DcType;

/*
* 监听地址到账信息
*/
class Tronlisten extends Command
{
    public static $debug = 0;
    public static $sync_sec = 15;//回写数据库时间
    public static $expire_time = 5*60;
    protected function configure(){
        $this->setName('Tronlisten')->setDescription("计划任务 Tronlisten");
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
        // Log::record('Tronlisten定时任务开启');
        // $this->output->writeln('Tronlisten running...');
        $model = new DcType();
        //首先监听已经被锁的数据
        $cond = [
            'status' => 1,
            'is_locked' => 1
        ];
        $data = $model->where($cond)->select();
        // dump($data);exit;
        if($data){
            foreach ($data as $item) {
                $lockTime = $item['lock_time']; // 将 lock_time 字段转换为时间戳
                $currentTime = time();

                // 判断当前时间是否超过 lock_time 5 分钟并且lock_time
                if (($currentTime - $lockTime) < self::$expire_time) {
                    //这里的逻辑是去tron主网查询
                    $this->transactions($item['address']);
                }
            }
        }else{
            //没有监听数据
        }
        
    }

    //tron记录查询方法
    public function transactions($address){
        dump($address);exit;
    }
}
