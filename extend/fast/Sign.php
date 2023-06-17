<?php

namespace fast;

/**
 * 签名验证
 */
class Sign
{

    /**
     * 生成签名
     *
     */
    public static function getSign($params,$key = '',$isdecode = true,$isstrtoupper = true)
    {
        $params = array_filter($params,'strlen');
        // dump($params);
        ksort($params);
        //reset()内部指针指向数组中的第一个元素
        // reset($params);
        $str = http_build_query($params);
        $str .= '&key='.$key;
        // dump($str);
        if($isdecode == true){
            $str = urldecode($str);
        }
        
        // dump($str);exit;
        if($isstrtoupper){
            $res = strtoupper(md5($str));
        }else{
            $res = md5($str);
        }
        // dump($res);exit;
        return $res;
    }

    /**
     * 验证签名
     *
     */
    public static function verifySign($params,$key = '',$isdecode = true,$isstrtoupper = true)
    {
        //传入的签名
        $sign = $params['sign'];
        unset($params['sign']);
        //系统的签名
        $sys_sign = self::getSign($params,$key,$isdecode,$isstrtoupper);
        // dump($sys_sign);
        if($sys_sign != $sign){
            return false;
        }else{
            return true;
        }
    }

    /**
     * 麒麟签名方法
     *
     */
    public static function makeSign($data,$key = '') {
        //去空
        $data = array_filter($data);
        //签名步骤一：按字典序排序参数
        ksort($data);
        $string_a = http_build_query($data);
        $string_a = urldecode($string_a);
        //签名步骤二：在string后加入KEY
        $string_sign_temp = $string_a . "&key=" . $key;
        //签名步骤三：MD5加密
        $sign = md5($string_sign_temp);
        //签名步骤四：所有字符转为大写
        $result = strtoupper($sign);
        return $result;
    }

    /**
     * 验证麒麟签名
     *
     */
    public static function KilinverifySign($params,$key = '')
    {
        //传入的签名
        $sign = $params['sign'];
        unset($params['sign']);
        //系统的签名
        $sys_sign = self::makeSign($params,$key);
        // dump($sys_sign);
        if($sys_sign != $sign){
            return false;
        }else{
            return true;
        }
    }

    /**
     * [encrypt aes加密]
     * @param    [type]                   $sStr [要加密的数据]
     * @param    [type]                   $sKey [加密key]
     * @return   [type]                         [加密后的数据]
     */
    public static function encrypt($input, $key)
    {
        $data = openssl_encrypt($input, 'AES-128-ECB', $key, OPENSSL_RAW_DATA);
        $data = base64_encode($data);
        return $data;
    }
    /**
     * [decrypt aes解密]
     * @param    [type]                   $sStr [要解密的数据]
     * @param    [type]                   $sKey [加密key]
     * @return   [type]                         [解密后的数据]
     */

    public static function decrypt($sStr, $sKey)
    {
        $decrypted = openssl_decrypt(base64_decode($sStr), 'AES-128-ECB', $sKey, OPENSSL_RAW_DATA);
        return $decrypted;
    }

    //只支持117位加密
    public static function setEncrypt($public_key,$data){
        $encrypted = "";
        $pu_key = openssl_pkey_get_public($public_key);
        openssl_public_encrypt($data,$encrypted,$pu_key);
        $encrypted = base64_encode($encrypted);
        return $encrypted;
        
    }

    //解密
    public static function setDecrypt($private_key,$data){
        $decrypted = "";
        $pi_key =  openssl_pkey_get_private($private_key);

        //这个函数可用来判断私钥是否是可用的，可用返回资源id Resource id
        openssl_private_decrypt(base64_decode($data),$decrypted,$pi_key);
        
        return $decrypted;
    }

    //BZ加密
    public static function bzencrypt($params,$secret = ''){
        // unset($params[0]);
        // dump($params);
        $data = [];
        if($params){
            foreach ($params as $k => $v) {
                $at = strstr($v, 'X-Qu');
                if($at){
                    $value = explode(':',$v);
                    $data[$value[0]] = $value[1];
                }
                
            }
        }
        // dump($data);
        ksort($data);
        $sign_str = http_build_query($data);
        // dump($sign_str);
        $sign = hash_hmac('sha256', $sign_str, $secret, false);


        
        // dump($str);
        $res = strtoupper($sign);
        // dump($res);exit;

        return $res;
    }

    /**
     * 验证BZ签名
     *
     */
    public static function bzdecrypt($params,$secret = '')
    {

        $data = [];
        if($params){
            foreach ($params as $k => $v) {
                $at = strstr($k, 'X-Qu');
                if($at && $k !== 'X-Qu-Signature'){
                    $data[] = $k.':'.$v;
                }
            }
        }

        //传入的签名
        $sign = isset($params['X-Qu-Signature'])?$params['X-Qu-Signature']:'';
        //系统的签名
        $sys_sign = self::bzencrypt($data,$secret);
        // dump($sign);
        // dump($params);
        // dump($sys_sign);
        // exit;
        if($sys_sign != $sign){
            return false;
        }else{
            return true;
        }
    }

    /**
     * 生成签名
     *
     */
    public static function getDsSign($params,$key = '')
    {
        $params = array_filter($params,'strlen');
        // dump($params);
        ksort($params);
        //reset()内部指针指向数组中的第一个元素
        // reset($params);
        $str = http_build_query($params);
        // $str .= '&key='.$key;
        $str = str_replace('+',' ',$str);
        // dump($str);
        // $str = urldecode($str);
        
        $res = md5($str);
        
        // dump($res);exit;

        return $res;
    }

    /**
     * 验证签名
     *
     */
    public static function verifyDsSign($params,$key = '')
    {
        //传入的签名
        $sign = $params['sign'];
        unset($params['sign']);
        //系统的签名
        $sys_sign = self::getDsSign($params,$key);
        // dump($sys_sign);
        if($sys_sign != $sign){
            return false;
        }else{
            return true;
        }
    }

    /**
     * 生成签名
     *
     */
    public static function getbkSign($params,$key = '')
    {

        $arr = $params['data'];

        $str = $key.json_encode($arr,JSON_FORCE_OBJECT);
        $res = md5($str);
        
        // dump($res);exit;

        return $res;
    }

    /**
     * 验证签名
     *
     */
    public static function verifybkSign($params,$key = '')
    {
        //传入的签名
        $sign = $params['sign'];
        unset($params['sign']);
        //系统的签名
        $sys_sign = self::getbkSign($params,$key);
        // dump($sys_sign);
        if($sys_sign != $sign){
            return false;
        }else{
            return true;
        }
    }

}
