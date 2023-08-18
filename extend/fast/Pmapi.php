<?php
namespace fast;

use fast\Http;
use think\Env;
use think\log;


//Pm2接口类
class Pmapi{
    public static function getpm2list(){
        try {
            $arrData  = array(
            );
            
            $url = Env::get('mail.url', '').'/pm2/list';

            // dump($url);exit;
            // dump($arrData);
            $res = Http::formpost($url,$arrData);

            if($res){
                $ret = json_decode($res,true);
                if($ret){
                    if(isset($ret['code']) && $ret['code'] == 200){
                        return $ret['data'];
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

?>