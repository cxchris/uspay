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
    public static $channel = 5;
    protected $PayOrder;
    protected $model;
    private $recordedErrors = [];
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
        // dump(time()+self::$expire_time);exit; //打印出五分钟后的时间

        //获取yd_dc_list里is_locked等于1的数据，然后遍历，当前时间超过lock_time 5 分钟的时候，就重置状态
        $cond = [
            'a.status' => 1,
            'a.is_locked' => 1,
        ];
        $data = $this->model->alias('a')
                        // ->join('yd_pay_order b', 'a.id = b.otc_id', 'LEFT')
                        ->where($cond)
                        ->field('a.*')
                        ->select();
        // echo $this->model->getLastsql();exit;
        // dump($data);exit;

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
        // $address = 'TUVVB3tkotrKW8xiYTwSTiCdhASEHj2Rza';
        $url = 'https://api.trongrid.io/v1/accounts/'.$address.'/transactions/trc20';

        $params = [
            'limit' => 10,
            'contract_address' => Env::get('dc.trc20ContractAddress', '')
        ];

        try {
            //先确订单,5分钟以内
            $order = $this->getOrder($item);
            // echo $this->PayOrder->getLastsql();exit;
            if(!$order){
                throw new \Exception('订单查询失败'.$order['id']);
            }

            //成功了就不去查找了
            if($order['status'] == 1){
                throw new \Exception('订单已经完成'.$order['id']);
            }

            if($order['status'] == 2){
                throw new \Exception('订单已经设置成失败了'.$order['id']);
            }

            $response = http::get($url, $params);
            if(!$response){
                throw new \Exception('订单'.$order['id'].':请求tron接口失败-address'.$address);
            }
            $data = json_decode($response, true);
            // if(!$data){
            //     throw new \Exception('订单'.$order['id'].'tron接口返回数据无法格式化-address'.$address);
            // }
            // if(!$data['data']){
            //     throw new \Exception('订单'.$order['id'].'用户还未付款'.$address);
            // }

            if ($data && is_array($data['data'])) {
                $this->processTransactions($data['data'], $order, $item);
            }else{
                throw new \Exception('订单'.$order['id'].'数据异常或未付款'.$address);
            }
        } catch (\Exception $e) {
            // dump($e);
            $errorMessage = $e->getMessage();
            if (!in_array($errorMessage, $this->recordedErrors)) {
                Log::error($errorMessage);
                $this->recordedErrors[] = $errorMessage;
            }
        }

        return true;
    }

    // 获取订单状态
    private function getOrder($item) {
        return $this->PayOrder
            ->alias('a')
            ->join('merchant c', 'a.merchant_number = c.merchant_number', 'LEFT')
            ->where(['otc_id' => $item['id'], 'channel_id' => self::$channel])
            ->where('a.create_time', '<', time() + self::$expire_time)
            ->field('a.*, c.merchant_key')
            ->order('a.id', 'desc')
            ->find();
    }

    // 处理交易记录
    private function processTransactions($transactions, $order, $item) {
        foreach ($transactions as $value) {
            $block_timestamp = $value['block_timestamp'] / 1000;
            $amount = $value['value'] / pow(10, 6);

            if ($block_timestamp < ($item['lock_time'] + self::$expire_time) &&
                $order['money'] == $amount && $order['address'] == $value['to']) {
                $status = 1;
                $this->output->writeln('回调...' . $item['id']);
                $this->PayOrder->callbackMerchant($order, $status);
                $this->PayOrder->check_pay_order($order);
                break;
            }
        }
    }

    //重置
    private function resetAddress($item = []){
        $id = $item['id'];
        $updateData = [
            'is_locked' => 0,
            'lock_time' => 0
            // 其他需要更新的字段
        ];

        $this->model->where('id', $id)->update($updateData);
        
        $this->output->writeln('重置...'.$id);
        return true;
    }
}
