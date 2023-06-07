<?php
namespace app\admin\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Log;
use think\Db;
use app\common\model\PayOrder;
use app\common\model\PaymentOrder;
use fast\Http;

/*
* 商户回调
*/
class Callback extends Command
{
    public static $debug = 0;
    public static $sync_sec = 60;//回写数据库时间

    protected function configure(){
        $this->setName('Callback')->setDescription("计划任务 Callback");
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
        // Log::record('Callback定时任务开启');
        $this->output->writeln('Callback running...');

        $model = new PayOrder();

        //查看所有的代付代收订单，进行中，已支付，支付失败，未通知订单，通知次数小于5
        $payrecord = $model
                ->field('a.*,c.merchant_key')
                ->alias('a')
                ->join('merchant c','a.merchant_number = c.merchant_number','LEFT')
                ->where('notify_status',2)
                ->where('notify_number','<',5)
                ->select();
        $this->call($payrecord,$model);

        $paymentmodel = new PaymentOrder();
        $paymentrecord = $paymentmodel
                ->field('a.*,c.merchant_key,c.merchant_number')
                ->alias('a')
                ->join('merchant c','a.merchant_id = c.id','LEFT')
                ->where('notify_status',2)
                ->where('notify_number','<',5)
                ->select();

        $this->call($paymentrecord,$paymentmodel);
    }


    private function call($record,$model){
        if($record){
            foreach($record as $k =>$v){
                $url = $v['notify_url'];
                $id = $v['id'];
                
                $data = $model->getCondItem($v,$v['status']);
                $res = Http::post($url, $data, $options = []);

                //记录回调时间，回调次数
                if($res){
                    if($res == 'success'){
                        $model->update_pay_order($id,1);
                    }else{
                        $model->update_pay_order($id,2);
                    }
                }else{
                    $model->update_pay_order($id,2);
                }
            }
        }

        return true;
    }
}
