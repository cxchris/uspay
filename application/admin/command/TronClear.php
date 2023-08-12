<?php
namespace app\admin\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Log;
use think\Db;
use think\Env;
use fast\Http;
use app\common\model\DcType;
use app\common\model\PayOrder;

/*
* 清理本地过期的地址
*/
class TronClear extends Command
{
    public static $debug = 0;
    public static $sync_sec = 20;//回写数据库时间
    public static $expire_time = 6*60;
    protected $PayOrder;
    protected $model;
    protected function configure(){
        $this->setName('TronClear')->setDescription("计划任务 TronClear");
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
        // Log::record('TronClear定时任务开启');
        // $this->output->writeln('TronClear running...');
        $this->model = new DcType();
        $this->PayOrder = new PayOrder();

        //获取yd_dc_list里is_locked等于1的数据，然后遍历，当前时间超过lock_time 5 分钟的时候，就重置状态
        $cond = [
            'a.status' => 1,
            'a.is_locked' => 1,
            'b.channel' => 5
        ];
        $data = $this->model->alias('a')
                        ->join('yd_pay_order b', 'a.id = b.otc_id', 'LEFT')
                        ->where($cond)
                        ->field('a.id as dcid,a.address,a.is_locked,lock_time, b.*')
                        ->select();
        echo $this->model->getLastsql();exit;

        // 遍历数据并处理
        if($data){
            foreach ($data as $item) {
                $lockTime = $item['lock_time']; // 将 lock_time 字段转换为时间戳
                $currentTime = time();

                // 判断当前时间是否超过 lock_time 5 分钟并且lock_time
                if (($currentTime - $lockTime) >= self::$expire_time) {
                    // 只有超过5分钟才重置状态，而付款成功就不重置了
                    $this->resetAddress($item);
                }else{
                    $this->transactions($item);
                }
            }
        }else{
            //空，不处理
        }
    }

    //tron记录查询方法
    public function transactions($item){
        // dump($address);exit;
        $address = $item['address'];
        $address = 'TUVVB3tkotrKW8xiYTwSTiCdhASEHj2Rza';
        $url = 'https://api.trongrid.io/v1/accounts/'.$address.'/transactions/trc20';

        $params = [
            'limit' => 10,
            'contract_address' => Env::get('dc.trc20ContractAddress', '')
        ];

        try {
            $response = http::get($url, $params);
            if(!$response){
                throw new \Exception('请求tron接口失败-address'.$address);
            }
            $data = json_decode($response, true);
            if(!$data){
                throw new \Exception('tron接口返回数据无法格式化-address'.$address);
            }
            if(!$data['data']){
                throw new \Exception('用户还未付款'.$address);
            }
            //便利最近的十条记录，看看有没有五分钟以内的，且金额是正确的付款
            // dump($data);exit;
            foreach($data['data'] as $value){
                $block_timestamp = $value['block_timestamp'] / 1000;
                $amount = $value['value'] / pow(10, 6);; //格式化金额
                // dump($block_timestamp);
                // dump($amount);exit;


                // if($block_timestamp < ($item['lock_time'] + self::$expire_time) && $item['money'] == $amount){
                    //匹配订单成功，修改订单状态，并且发起回调
                    $status = 1;
                    // dump($item);exit;
                    $this->output->writeln('回调...'.$item['dcid']);
                    $this->PayOrder->callbackMerchant($item,$status);
                    //成功了之后还需要重置
                    $this->resetAddress($item);

                    break; // 跳出内层循环
                // }
            }
        } catch (\Exception $e) {
            // dump($e);
            Log::error($e->getMessage());
        }

        return true;
    }

    //重置
    private function resetAddress($item = []){
        $id = $item['dcid'];
        $updateData = [
            'is_locked' => 0,
            'lock_time' => 0
            // 其他需要更新的字段
        ];

        $this->model->where('id', $id)->update($updateData);

        //判断
        if($where){
            //还需要修改下订单的状态
            $this->PayOrder->where(array('id'=>$item['id']))->update(['update_time'=>time(),'status'=>$item['status']]);
        }
        
        $this->output->writeln('重置...'.$id);
        return true;
    }
}
