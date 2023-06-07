<?php
namespace app\admin\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Log;
use think\Db;
use Faker\Factory;
use fast\Http;

/*
* 假数据
*/
class Faker extends Command
{
    protected function configure(){
        $this->setName('fakers')->setDescription("计划任务 fakers");
    }

    protected function execute(Input $input, Output $output){
        $output->writeln('Date Crontab job start...');
        /*** 这里写计划任务列表集 START ***/

        $this->insert_good();              // 调用方法

        /*** 这里写计划任务列表集 END ***/
        $output->writeln('Date Crontab job end...');
    }

    private function insert_good(){
        $data = [
            'name' => '5 Pcs Place Setting (solid handle knife) | Gio Ponti Vintage', //secrete gift
            'market_price' => '99',
            'sale_price' => '99',
            'stock' => 200,
            'is_on_shelf' => 1,
            'description' => '',
            'note' => '',
            'create_time' => time(),
        ];
        Db::name('product')->insert($data);
    }

    private function create_good(){         // 逻辑

        for ($i=1; $i < 191; $i++) { 
            $res = Http::get('https://admin-api.vitalshop.me/product?page='.$i, [], $options = [],6);
            if($res){
                $res = json_decode($res,true);

                $res = $res['data']['on_shelf'];
                foreach($res as $k => $v){
                    $data = [
                        'name' => $v['product_name'],
                        'market_price' => $v['market_price'],
                        'sale_price' => $v['sale_price'],
                        'stock' => $v['stock'],
                        'is_on_shelf' => $v['is_on_shelf'],
                        'description' => $v['description'],
                        'note' => $v['remark'],
                        'create_time' => time(),
                    ];
                    Db::name('product')->insert($data);
                }
            }
        }
        

    }

    private function domain(){         // 逻辑
        
        $faker = Factory::create('en-in');//选择中文

        // dump($faker->unixTime);exit;
       

        // $data = [
        //     $faker->name,//随机姓名
        //     $faker->address,//随机地址
        //     $faker->email,//随机邮箱
        //     // $faker->e164PhoneNumber,
        //     substr_replace($faker->e164PhoneNumber,'+91',0,4),//电话
        //     // $faker->numberBetween(20,60),//年龄随机在20-60之间
        // ];
        
        // dump(substr('+918581001465',3));exit;

        for ($i=0; $i < 658; $i++) { 
            $x = rand(6,9);
            $data = [
                'username' => $faker->name,
                'sex' => rand(1,2),
                // 'mobile' => $faker->e164PhoneNumber,
                'mobile' => substr_replace($faker->e164PhoneNumber,'+91'.$x,0,5),
                'address' => str_replace("\n","",$faker->address),
                'email' => $faker->email,
                'create_time' => $faker->unixTime,

            ];
            Db::name('customer')->insert($data);
        }

    }
}
