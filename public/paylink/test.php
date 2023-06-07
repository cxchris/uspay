<?php
$key = '27f1bfa4cb0a42e7bfd2fe375e6f50a1';
$curl = curl_init();


$data = [
  'merchantNo' => 'Z16469229818290',
  'merchantSn' => 'T'.qrandom(8),
  'amount' => rand(1,3),
  'notifyUrl' => 'http://localhost:88/api/Pay/order',
  'time' => time(),
  // 'channel' => 1,
];
$data['sign'] = getSign($data,$key);

$string = http_build_query($data);

curl_setopt_array($curl, array(
  CURLOPT_URL => 'https://onepayhk.com/api/Pay/order',
  // CURLOPT_URL => 'http://localhost:88/api/Pay/order',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 0,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_POSTFIELDS => $string,
  CURLOPT_HTTPHEADER => array(
    'Content-Type: application/x-www-form-urlencoded'
  ),
));

$response = curl_exec($curl);

curl_close($curl);
if($response){
  $res = json_decode($response,true);
  // var_dump($res);
  header('Location:'.$res['data']['payUrl']);
}



function qrandom($len = 8){
  $pool = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
  return substr(str_shuffle(str_repeat($pool, ceil($len / strlen($pool)))), 0, $len);
}

/**
 * 生成签名
 *
 */
function getSign($params,$key = '',$isdecode = true,$isstrtoupper = true)
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
  
  // dump($str);
  if($isstrtoupper){
      $res = strtoupper(md5($str));
  }else{
      $res = md5($str);
  }
  
  // dump($res);exit;
  return $res;
}