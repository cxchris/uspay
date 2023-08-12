<?php

namespace app\common\model;

use think\Model;
use think\Session;
use think\Db;
use fast\Sign;
use fast\Http;
use think\Log;
use think\Process;

class OtcList extends Model
{
    protected $name = 'otc_list';
    
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $create_time = 'create_time';
    protected $updateTime = '';


    /*
    * 获取统计数据
    */
    public function getpaydata($id = 0,$merchant_number = '',$starttime,$endtime,$datetime){

        // 代收金额,代收手续费
        $where = [
            'status' => 1,
        ];
        if($id != 0){
            $where['merchant_number'] = $merchant_number;
        }

        $field = 'sum(money) AS money,sum(account_money) AS account_money, sum(rate_money) AS rate_money';
        
        $res = $this
        ->where('create_time', 'between time', [$starttime, $endtime])
        ->where($where)
        ->field($field)
        ->select();

        //代收未结算
        $where['is_billing'] = 0;
        $nobilling = $this
        ->where('create_time', 'between time', [$starttime, $endtime])
        ->where($where)
        ->sum('account_money');

        $data = [
            'merchant_id' => $id,
            'datetime' => $datetime,
            'amount' => $res[0]['money']??0,
            'amount_tax' => $res[0]['rate_money']??0,
            'amount_check' => $res[0]['account_money']??0,
            'amount_settlement' => $nobilling??0,
        ];

        return $data;
    }

    //node脚本处理
    public function node_exce($params,$ids){
        //判断node脚本是否启动，如果启动，就先关闭了，再重新修改配置，修改完成后再次启动，没有启动就直接生成配置，生成后再重启，如果启动失败了，那么则捕获异常出来
        
        // dump($nodeScriptPath);exit;

        // 判断 Node.js 脚本是否正在运行
        $isRunning = $this->checkNodeScriptRunning($params);
        // dump($isRunning);exit;


        // 修改配置
        $this->modifyConfig($params,$ids);
        
        $pid = 0;
        if($params['status'] == 1){
            //启动node脚本
            $pid = $this->startNodeScript($ids);
        }
        
        return $pid;
    }

    // 检查 Node.js 脚本是否正在运行
    private function checkNodeScriptRunning($params)
    {
        self::where(array('id'=>$params->id))->update(['pid'=>0,'status'=>0]);
        // 使用适当的方法检查 Node.js 脚本是否正在运行
        // 返回 true 表示正在运行，返回 false 表示未运行
        // 使用示例
        $pid = $params->pid;
        if ($this->isWindows()) {
            // Windows 系统
            // 执行 Windows 相关的逻辑
            if($pid == 0){
                return false;
            }else{
                //有pid，说明正在运行，检查下运行情况，看是否断了
                // 构造用于检查进程是否存在的命令
                $command = "tasklist | findstr ".$pid;

                // 执行命令并获取输出
                exec($command, $output);
                // dump($output);exit;
                if(!empty($output)){
                    $this->stopNodeScript($pid);
                }

                // 如果输出不为空，则表示进程存在
                return !empty($output);
            }
        } else {
            // Linux 系统
            // 执行 Linux 相关的逻辑
            if($pid == 0){
                return false;
            }else{
                $processid = $this->getcommand($params->id);
                $isProcessRunning = $processid == 0 ? false : true;

                if($isProcessRunning){
                    $this->stopNodeScript($processid);
                }

                // 如果输出不为空，则表示进程存在
                return $isProcessRunning;
            }
        }
    }

    // 停止正在运行的 Node.js 脚本
    public function stopNodeScript($pid)
    {
        // 使用适当的方法停止正在运行的 Node.js 脚本
        if ($this->isWindows()) {
            // $command = 'taskkill /F /PID '.$pid;
            $command = 'powershell Stop-Process -Id '.$pid;
        } else {
            $command = 'kill -9 '.$pid;
        }

        exec($command, $output);
    }

    // 修改配置
    private function modifyConfig($params,$ids)
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

    // 启动 Node.js 脚本
    private function startNodeScript($ids)
    {
        $file = ROOT_PATH.'/mail/src/'.$ids.'.js'; // Node.js 脚本路径
        // 使用适当的方法启动 Node.js 脚本
        $sourceFile = ROOT_PATH.'/mail/src/example.js';
        if (!file_exists($file)) {
            if (copy($sourceFile, $file)) {
                // echo '文件拷贝成功。';
            } else {
                // echo '文件拷贝失败。';
            }
        }

        $path = ROOT_PATH.'/mail/src/';

        // dump($path);exit;
        //然后执行nodejs
        $pid = 0;
        if ($this->isWindows()) {
            $drive = substr($path, 0, 1);
            $convertedPath = str_replace('\\', '/', $path);

            // 生成最终的命令
            $command = 'start /B cmd /c "cd /' . $drive .' '. $convertedPath . ' && '.'node '.$ids.'.js > NUL 2>&1"';
            // $command = 'start /B cmd /c "cd /d D:\project\trunk\uspay\mail\src && node 8.js > NUL 2>&1"';
            // dump($command);exit;
            // 创建进程对象
            $process = new Process($command);

            // 运行进程
            $process->run();

            // 获取进程的输出
            $pid = $this->getcommand($ids);
        }else{
            $convertedPath = str_replace('\\', '/', $path);
            // $command = 'nohup node ' . $convertedPath .$ids. '.js > /dev/null 2>&1 &';
            // $nodeCommand = shell_exec('/usr/bin/which node');
            // dump($nodeCommand);exit;

            $command = 'cd '.$convertedPath.' && nohup /usr/local/bin/node '.$ids.'.js > /home/wwwroot/default/uspay/mail/logs/output.log 2>&1 &';

            $process = new Process($command);

            // 运行进程
            $process->run();

            $pid = $this->getcommand($ids);
            // dump($pid);exit;
        }

        //生成pid之后，插入数据库
        self::where(array('id'=>$ids))->update(['pid'=>$pid,'status'=>1]);

        return $pid;
    }

    //获取pid
    public function getcommand($ids){
        $pid = 0;
        if ($this->isWindows()) {
            // 获取进程的输出
            $command = 'wmic process where "commandline like \'%'.$ids.'.js%\'" get processid, commandline';

            // 执行命令并获取输出
            exec($command, $output);
            if($output){
                foreach ($output as $k => $v) {
                    if (preg_match("/js\s+(\d+)/", $v, $matches)) {
                        $pid = $matches[1];
                    }
                }
            }

        }else{
            // $command = 'pgrep -f '.$ids.'.js';
            $command = 'ps -ef |grep '.$ids.'.js';
            exec($command, $output);

            if($output){
                // $firstProcess = $output[0];
                // $pattern = '/\b(\d+)\b.*\/root\/\.nvm\/versions\/node\/v14\.21\.3\/bin\/node/';
                // preg_match($pattern, $firstProcess, $matches);

                // if (isset($matches[1])) {
                //   $pid = $matches[1];
                //   // echo $pid; // Output: 2221552
                // }

                foreach ($output as $process) {
                    if (strpos($process, '/usr/local/bin/node '.$ids.'.js') !== false) {
                        $pid = preg_replace('/\s+/', ' ', $process);
                        $pid = explode(' ', $pid)[1];
                    }
                }

            }

            // dump($output);exit;
        }

        return $pid;
    }

    //获取app.js的运行情况
    public function getappcommand(){
        $pid = 0;
        if (model('OtcList')->isWindows()) {
            // 获取进程的输出
            $command = 'wmic process where "commandline like \'%app.js%\'" get processid, commandline';

            // 执行命令并获取输出
            exec($command, $output);
            if($output){
                foreach ($output as $k => $v) {
                    if (preg_match("/js\s+(\d+)/", $v, $matches)) {
                        $pid = $matches[1];
                    }
                }
            }

        }else{
            // $command = 'pgrep -f '.$ids.'.js';
            $command = 'ps -ef |grep app.js';
            exec($command, $output);

            if($output){
                foreach ($output as $process) {
                    if (strpos($process, '/usr/local/bin/node '.$ids.'.js') !== false) {
                        $pid = preg_replace('/\s+/', ' ', $process);
                        $pid = explode(' ', $pid)[1];
                    }
                }

            }

            // dump($output);exit;
        }

        return $pid;
    }

    //启动app.js
    public function startnodeapp(){
        $path = ROOT_PATH.'/tron/';
        if ($this->isWindows()) {
            $drive = substr($path, 0, 1);
            $convertedPath = str_replace('\\', '/', $path);

            // 生成最终的命令
            $command = 'start /B cmd /c "cd /' . $drive .' '. $convertedPath . ' && '.'node app.js > NUL 2>&1"';
            // $command = 'start /B cmd /c "cd /d D:\project\trunk\uspay\mail\src && node 8.js > NUL 2>&1"';
            // dump($command);exit;
            // 创建进程对象
            $process = new Process($command);

            // 运行进程
            $process->run();

            // 获取进程的输出
            $pid = $this->getappcommand();
        }else{
            $convertedPath = str_replace('\\', '/', $path);

            $command = 'cd '.$convertedPath.' && nohup /usr/local/bin/node app.js > /home/wwwroot/default/uspay/tron/logs/output.log 2>&1 &';

            $process = new Process($command);

            // 运行进程
            $process->run();

            $pid = $this->getappcommand();
            // dump($pid);exit;
        }
        return true;
    }

    public function isWindows()
    {
        $os = strtoupper(PHP_OS);

        return (substr($os, 0, 3) === 'WIN');
    }
}
