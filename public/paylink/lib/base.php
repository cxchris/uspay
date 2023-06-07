<?php
include 'config.php';
include 'mysql.php';

class base{
    public $mysql;
    public $key = 'qwerty';
    public $url = 'https://securegw-stage.paytm.in';
    public function __construct() {
        $this->ResponseHandler();
    }

    private function ResponseHandler() {
        $conf = Config::mysql();
        $this->mysql = new MysqlDriver($conf);
    }
    
    //html
    public function error_html($msg){
        $html = '<meta name="viewport" content="width=device-width,initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no"/>
                <center>
                 <h1>'.$msg.'</h1>
              </center>';
        echo $html;
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
}
?>