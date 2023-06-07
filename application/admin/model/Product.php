<?php

namespace app\admin\model;

use think\Model;
use think\Session;

class Product extends Model
{
    protected $name = 'product';
    
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $create_time = 'create_time';
    protected $update_time = 'update_time';
    protected $x = 0;
    protected $data = [];
    protected $field = 'id,sale_price';


    /*
    * 获取随机商品
    */
    public function getrandomgood($price = 0){
        $data = [];

        //搜索数据库里有无该价格的商品
        $is_sale = $this->where(['sale_price'=>$price])->find();
        if($is_sale){
            //随机判断是组合商品还是单独商品
            $x = rand(0,1);
            // $x = 0;
            if($x == 0 && $price > 1){
                //组合商品
                //取第一个商品的策略
                $one = $this->getzhgood($price)->toArray();
                // dump($one);exit;
                $another = $this->getgoodarr($price,$one);

                array_push($another, $one);
                $data = $another;

            }else{
                //单独商品
                $good = $this->field($this->field)->where(['sale_price'=>$price])->orderRaw('rand()')->find()->toArray();
                $good['num'] = 1;
                $data[] = $good;
            }
        }else{
            //组合商品
            //取第一个商品的策略
            $one = $this->getzhgood($price)->toArray();
            $another = $this->getgoodarr($price,$one);

            array_push($another, $one);
            $data = $another;
        }
        return $data;
    }

    //获取所有的商品
    private function getgoodarr($price,$one){
        $poor = $price - $one['sale_price']*$one['num'];
        if($poor == 0){
            return [];
        }
        // dump($poor);exit;
        //获取第二个商品，直到获取到为止
        $good = $this->field($this->field)->where(['sale_price'=>$poor])->orderRaw('rand()')->find();
        // dump($good);exit;
        if($good ){
            $good['num'] = 1;
            $this->data[$this->x] = $good->toArray();
            return $this->data;
        }else{
            //获取第二个商品，直到获取到为止
            $one = $this->getzhgood($poor);
            // dump($one);exit;
            $this->data[$this->x] = $one->toArray();
            $this->x++;
            return $this->getgoodarr($poor,$one);
        }
    }

    // private function getzhgood($price){
    //     $y = rand(0,1);
    //     // $y = 1;
    //     // if($y == 0){
    //     //     //取第一个商品的策略，取大值

    //     // }else{
    //         //取第一个商品的策略，总额分一半
    //         if($price > 2000 && $y == 0){
    //             $tprice = ceil($price / rand(2,6));
    //         }else{
    //             $tprice = ceil($price / rand(2,5));
    //         }
    //         $tprice = $tprice - $tprice%10;
    //     // dump($tprice);
    //     // dump($tprice%10);
    //     // $tprice = $tprice - $tprice%10;

    //     // dump($tprice);
    //     // exit;

    //         if($tprice <= 0){
    //             $tprice = $price%10;
    //         }
    //     // }

    //     dump($tprice);exit;
    //     $ret = $this->where(['sale_price'=>$tprice])->orderRaw('rand()')->find();
    //     if($ret){
    //         return $ret;
    //     }else{
    //         return $this->getzhgood($tprice);
    //     }
    // }

    //获取组合商品
    private function getzhgood($price){
        if($price == 0){
            return [];
        }
        $y = rand(0,1);
        // $y = 1;

        //取第一个商品的策略，总额分一半
        $rand = rand(2,6);
        // dump($rand);
        // $rand = 6;
        $num = 1;
        $yu = 10;
        if($price%$yu == 0){
            $price = $price - rand(1,9);
        }

        // dump($price);
        // dump($rand);

        $tprice = ceil($price / $rand);
        // dump($tprice);
        // dump($tprice%$yu);
        $tprice = $tprice - $tprice%$yu;

        // dump($tprice);

        if($tprice > 0 && $rand > 2 && $y == 0){
            //超过2，意味着num可以为rand - 1
            $num = $rand - 1;
        }

        if($tprice <= 0){
            $tprice = $price%$yu;
        }


        // dump($num);
        // dump($tprice);

        $ret = $this->field($this->field)->where(['sale_price'=>$tprice])->orderRaw('rand()')->find();
        if($ret){
            $ret['num'] = $num;
            return $ret;
        }else{
            $sum_tprice = $tprice*$num;
            return $this->getzhgood($sum_tprice);
        }
    }

}
