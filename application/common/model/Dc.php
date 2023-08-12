<?php

namespace app\common\model;

use think\Model;
use think\Session;
use think\Db;
use fast\Sign;
use fast\Http;
use think\Log;
use think\Process;
use think\Env;


class Dc extends Model
{
    protected $name = 'dc_list';
    
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $create_time = 'create_time';
    protected $updateTime = '';
    const url = '/account/usdtbalance';


    // 生成新的 USDT 钱包
    public function generateUSDTWallet()
    {
        try {
            $arrData  = array(
                'name' => 'tron',
            );

            $sign = Sign::getSign($arrData,Env::get('dc.key', ''));
            $arrData['sign'] = $sign;

            
            $url = Env::get('dc.url', '').self::url;

            // dump($url);exit;
            // dump($arrData);
            $res = Http::formpost($url,$arrData);

            if($res){
                $ret = json_decode($res,true);
                dump($ret);exit;
                if($ret){
                    if(isset($ret['code']) && $ret['code'] == 200){
                        return json($ret);
                    }else{
                        throw new \Exception($ret['msg']);
                    }
                }else{
                    throw new \Exception('Interface exception');
                }
            }else{
                throw new \Exception('service no start');
            }
        } catch (\Exception $e) {
            // $this->error($e->getMessage());
            return $e;
        }
    }
}
