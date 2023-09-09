<?php

namespace fast;

use think\log;
use fast\Pmapi;
use app\common\model\OtcList;

/**
 * Pm2管理
 */
class Pm2
{
    //使用pm2来执行，执行逻辑：1，确认执行文件，2，确认配置文件，3，运行进程
    public static function exce($params,$ids){
        $id = $params['id'];
        $file = ROOT_PATH.'/mail/src/'.$id.'.js'; // Node.js 脚本路径
        // 先判断脚本文件是否存在，如果没有就先拷贝一份
        $sourceFile = ROOT_PATH.'/mail/src/example.js';
        if (!file_exists($file)) {
            if (copy($sourceFile, $file)) {
                // echo '文件拷贝成功。';
            } else {
                // echo '文件拷贝失败。';
            }
        }

        // 修改配置，然后运行
        self::modifyConfig($params,$ids);

        // //pm2重启
        // self::checkNodeScriptRunningpm2($params);
        
        // //运行
        if($params['status'] == 1){
            //启动node脚本
            $cond = [
                'id' => $params['id'],
                'type' => 'mail'
            ];
            self::startNodeScriptpm2($cond);
        }else{
            self::stopNodeScriptpm2($params);
        }

        return true;
    }

    //启动
    public static function startNodeScriptpm2($params){
        // $path = ROOT_PATH.'/mail/src/';
        // $pid = 0;
        
        // $command = 'pm2 start '.$path.$id.'.js --name="'.$id.'"';
        // dump($command);exit;

        // $output = shell_exec($command);

        $cond = [
            'id' => $params['id'],
            'type' => $params['type']??'mail',
        ];

        $res = Pmapi::pm2()->start($cond);

        $pid = $params['id'];

        // $model = new OtcList();
        // $model->where(array('id'=>$id))->update(['pid'=>$pid,'status'=>1]);

        return $pid;
    }

    //检查所有状态-去请求node接口试试
    public static function checkScriptAllStatus(){
        // $command = 'pm2 list';
        // $command = '/root/.nvm/versions/node/v15.0.1/bin/pm2 list 2>&1';
        // $command = 'python3 /home/wwwroot/default/uspay/mail/pm2/list.py 2>&1';
        // $output = shell_exec($command);
        // // dump($output);exit;
        // $tableData = self::findstr($output);
        // return $tableData;

        $tableData = Pmapi::pm2()->list();
        return $tableData;
    }

    //根据单个id检查状态
    public static function checkScriptStatus(?int $id) :bool{
        $status = false;
        // $command = 'pm2 list';
        // $output = shell_exec($command);
        $tableData = Pmapi::pm2()->list();
        $status = self::foreachListOnlie($id,$tableData);
        return $status;
    }

    //用pm2来控制关闭方法
    public static function stopNodeScriptpm2($params){
        // 使用适当的方法停止正在运行的 Node.js 脚本
        // if (self::isWindows()) {
        //     // $command = 'taskkill /F /PID '.$pid;
        //     $command = 'pm2 stop '.$params['id'];
        //     $output = exec($command, $output);

        // } else {
        //     $command = 'kill -9 '.$pid;
        //     exec($command, $output);
        // }
        $cond = [
            'id' => $params['id'],
        ];
        $res = Pmapi::pm2()->stop($cond);
        return $res;
    }

    //便利状态
    private static function foreachListOnlie($name,$tableData){
        // $tableData = self::findstr($output);
        $isOnline = false; // 标记是否找到匹配且状态为 online 的项

        if($tableData){
            foreach ($tableData as $row) {
                if ($row["name"] == $name && $row["status"] == "online") {
                    $isOnline = true;
                }
            }
        }
        
        return $isOnline;
    }

    //换过一种方法，从pm2 list里查找字符串，先转换成json再操作
    private static function findstr(?string $string) :array{
        $arr = self::toTable($string);
        return $arr??[];
    }

    //转化为table数组
    public static function toTable($input){
        $lines = explode("\n", trim($input));
        $headers = array_map('trim', array_filter(explode('│', $lines[1])));
        $headers = array_values($headers); // 重新索引数组
        $tableData = [];

        for ($i = 3; $i < count($lines) - 1; $i++) {
            $cells = array_map('trim', array_filter(explode('│', $lines[$i])));

            $cells = array_values($cells); // 重新索引数组
            $rowData = [];

            for ($j = 0; $j < count($headers); $j++) {
                $rowData[$headers[$j]] = $cells[$j];
            }

            $tableData[] = $rowData;
        }

        return $tableData;
        // $jsonData = json_encode($tableData, JSON_PRETTY_PRINT);
    }

    //修改基础配置
    public static function modifyConfig($params,$ids)
    {
        // 根据你的需求修改配置
        $filename = ROOT_PATH.'/mail/config/'.$ids.'.json'; // 配置路径
        $json = [
            'user' => $params['email'],
            'password' => $params['password'],
            'host' => $params['host'],
            'port' => $params['port'],
            'tls' => true,
            'tlsOptions' => [
                'rejectUnauthorized' => false
            ],
            'channel_id' => $params['channel_id']
        ];
        $json = json_encode($json,true);
        file_put_contents($filename, $json);
        // dump($json);exit;
    }

    public static function isWindows()
    {
        $os = strtoupper(PHP_OS);

        return (substr($os, 0, 3) === 'WIN');
    }

}
