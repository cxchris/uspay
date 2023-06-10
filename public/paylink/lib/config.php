<?php
ini_set('error_reporting', 'E_ALL ^ E_NOTICE');
date_default_timezone_set('America/New_York');

class Config {
	/* 数据库连接参数 */
    public static function mysql(){
        switch ($_SERVER['HTTP_HOST']) {
            //本地
            case 'localhost':
                $mysql_config = array(
                    'host'     => '127.0.0.1',
                    'port'     => 3306,
                    'username' => 'root',
                    'password' => 'awshaiwai@2022',
                    'dbname'   => 'yddb',
                    'charset'  => 'utf8',
                );
                break;
            default:
				$mysql_config = array(
                    'host'     => '127.0.0.1',
                    'port'     => 3306,
                    'username' => 'root',
                    'password' => 'awshaiwai@2022',
                    'dbname'   => 'yddb',
                    'charset'  => 'utf8',
                );
                break;
        }
        return $mysql_config;
    }

}
?>