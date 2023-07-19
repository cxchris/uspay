import axios from 'axios';
// import qs from 'qs';
import { getSign } from './sign.js';
import log4js from './log4.js';

//定义支付渠道类型
const CASHAPP = 1;
const ZELLE = 4;

//通知地址
const url = 'https://onepayus.com/api/upnotice/callback';
// const url = 'http://127.0.0.1:1818/api/upnotice/callback';
const success = 1;
const appname = 'Cash App';
const zellename = 'Bank of America';
const key = 'B3iYKkRHlmUanQGaNMIJziWOkNN9dECQQD';

function send(fileName,data,channel_id){
  const logger = log4js.getLogger(fileName+'.js');
  //解读from和subject，1，from符合是cash app，2，subject截取金额和备注(for后面的是备注)
  if(data){
    const from = data.from; // 获取 from
    const subject = data.subject; // 获取 subject
    
    // console.log(from)
    // console.log(subject);

    //如果是zelle的类型，就不用判断
    // 解读 from
    const isFromCashApp = from.includes(appname); // 检查 from 是否包含 "cash app"
    const isFromCashZelle = from.includes(zellename); // 检查 from 是否包含 "America"

    if(channel_id == CASHAPP && !isFromCashApp){
      logger.error('非cash app:', subject);
      return null;
    }

    // if(channel_id == ZELLE && !isFromCashZelle){
    //   logger.error('非zelle:', subject);
    //   return null;
    // }

    let postData = {
      amount: data.amount,
      pkg: appname,
      note: data.note,
      containsContinue: data.containsContinue,
      containsReceived: data.containsReceived,
      time: Date.now()
    };

    postData.sign = getSign(postData,key);
    logger.info('发送 msg:',JSON.stringify(postData));
    // console.log(postData)
    
    axios.post(url, postData)
      .then((response) => {
        logger.info('响应 msg:',response.data);
        logger.info('================================================');
        console.log(response.data); // 响应数据
      })
      .catch((error) => {
        logger.error('响应error:', error);
        console.error(error);
      });
  }

  return null;
}

export default send;