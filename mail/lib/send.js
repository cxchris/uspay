import axios from 'axios';
// import qs from 'qs';
import { getSign } from './sign.js';
import log4js from './log4.js';

//通知地址
// const url = 'https://onepayus.com/api/upnotice/callback';
const url = 'http://127.0.0.1:1818/api/upnotice/callback';
const success = 1;
const appname = 'Cash App';
const key = 'B3iYKkRHlmUanQGaNMIJziWOkNN9dECQQD';

function send(fileName,data){
  const logger = log4js.getLogger(fileName+'.js');
  //解读from和subject，1，from符合是cash app，2，subject截取金额和备注(for后面的是备注)
  if(data){
    const from = data.from; // 获取 from
    const subject = data.subject; // 获取 subject
    const containsContinue = data.containsContinue; // 获取 containsContinue
    const containsReceived = data.containsReceived; // 获取 containsReceived
    
    // console.log(from)
    // console.log(subject);
    // console.log(containsContinue)
    // console.log(containsReceived)
    
    // 解读 from
    const isFromCashApp = from.includes(appname); // 检查 from 是否包含 "cash app"
    console.log(isFromCashApp);
    //cash app才去操作
    if(isFromCashApp){
      // 提取金额
      const amountRegex = /\$([0-9.]+)/; // 匹配 $ 符号后面的数字
      const amountMatch = subject.match(amountRegex);
      const amount = amountMatch ? amountMatch[1].trim() : null; // 提取匹配到的数字

      // 提取备注
      const noteRegex = /for\s+(.*?)\s+/; // 匹配 "for" 后面的字符串
      const noteMatch = subject.match(noteRegex);
      const note = noteMatch ? noteMatch[1].trim() : ''; // 提取匹配到的字符串

      console.log('"'+amount+'"')
      console.log('"'+note+'"');

      let postData = {
        amount: amount,
        pkg: appname,
        note: note,
        containsContinue: containsContinue,
        containsReceived: containsReceived,
        time: Date.now()
      };

      postData.sign = getSign(postData,key);
      console.log(postData)
      
      axios.post(url, postData)
        .then((response) => {
          logger.info('响应 msg:',response.data);
          console.log(response.data); // 响应数据
        })
        .catch((error) => {
          logger.error('响应error:', error);
          console.error(error);
        });
    }
  }else{
    logger.error('非cash app:', subject);
  }

  return null;
}

export default send;