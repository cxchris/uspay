import Imap from 'imap';
import log4js from './log4.js';
import send from './send.js';
import { simpleParser } from 'mailparser';
import cheerio from 'cheerio';

//定义支付渠道类型
const CASHAPP = 1;
const ZELLE = 4;

export function startEmailListener(config,fileName) {
  const imap = new Imap(config);
  // 获取 Logger 实例
  const logger = log4js.getLogger(fileName+'.js');

  imap.once('ready', function() {
    imap.openBox('INBOX', true, function(err, box) {
      if (err) throw err;
      logger.info('连接成功，等待新邮件到达...'+'当前进程 ID:'+process.pid);
      console.log('连接成功，等待新邮件到达...');

      imap.on('mail', function(numNewMsgs) {
        console.log(`收到新邮件: ${numNewMsgs} 封`);

        const f = imap.seq.fetch(box.messages.total + ':*', { bodies: '' });

        f.on('message', function(msg, seqno) {
          console.log(`邮件序号: ${seqno}`);

          msg.on('body', function(stream, info) {
            let buffer = '';
            stream.on('data', function (chunk) {
              buffer += chunk.toString('utf8');
            });
            stream.on('end', function () {
              const parsed = Imap.parseHeader(buffer);
              // console.log(parsed.from)
              // console.log(parsed.subject);

              //发送数据
              send(fileName,parsed);

              logger.info('From:',parsed.from);
              logger.info('Subject:',parsed.subject);
              console.log('=================================');
            });
          });
        });

        f.once('error', function(err) {
          logger.info('获取邮件信息出错:'+err);
          console.error('获取邮件信息出错:', err);
        });

        f.once('end', function() {
          console.log('所有新邮件的信息已获取完毕');
        });
      });
    });
  });

  imap.once('error', function(err) {
    logger.info('IMAP 错误:'+err);
    console.error('IMAP 错误:', err);
  });

  imap.once('end', function() {
    console.log('与 IMAP 服务器的连接已关闭');
  });

  imap.connect();
}

export function readEmailListener(config,fileName) {
  //这里需要去掉json中的channel_id，再new下config
  let channel_id = config.channel_id
  delete config.channel_id;
  
  const imap = new Imap(config);
  // 获取 Logger 实例
  const logger = log4js.getLogger(fileName+'.js');

  imap.once('ready', function() {
    imap.openBox('INBOX', true, function(err, box) {
      if (err) throw err;
      logger.info('连接成功，等待新邮件到达...'+'当前进程 ID:'+process.pid);
      console.log('连接成功，等待新邮件到达...');

      imap.on('mail', function(numNewMsgs) {
        console.log(`收到新邮件: ${numNewMsgs} 封`);
        logger.info(`收到新邮件: ${numNewMsgs} 封`);

        const f = imap.fetch(box.messages.total + ':*', { bodies: '' });

        // 当获取到邮件时
        f.on('message', (msg) => {
          msg.on('body', (stream, info) => {
            // 解析邮件正文
            simpleParser(stream, (parseErr, parsed) => {
              if (parseErr) {
                logger.info('解析邮件正文错误:'+parseErr);
                console.error(parseErr);
                return;
              }

              // 获取主题
              const subject = parsed.subject;

              // 获取发件人信息
              const from = parsed.from.text;

              // 获取纯文本正文
              // const textBody = parsed.text;

              // 获取 HTML 正文
              const htmlBody = parsed.html;
              // console.log('HTML Body:', htmlBody);

              // 使用cheerio加载HTML
              const $ = cheerio.load(htmlBody);

              let containsContinue;
              let containsReceived;
              if(channel_id == CASHAPP){
                //cash APP类型

                // 在所有文本内容中查找是否存在"Continue"字符
                containsContinue = $('body').text().includes('Continue');
                // 在所有文本内容中查找是否存在"Received"字符
                containsReceived = $('body').text().includes('Received');
              }else if(channel_id == ZELLE){
                //zelle类型
                containsContinue = false;
                containsReceived = true;
              }

              // 输出结果
              console.log('Subject:', subject);
              console.log('From:', from);
              console.log('是否存在 "Continue":', containsContinue);
              console.log('是否存在 "Received":', containsReceived);

              logger.info('From:',from);
              logger.info('Subject:',subject);
              logger.info('是否存在 "Continue":', containsContinue);
              logger.info('是否存在 "Received":', containsReceived);

              const data = {
                from:from,
                subject:subject,
                containsContinue:containsContinue?1:0,
                containsReceived:containsReceived?1:0,
              };

              //发送数据
              send(fileName,data,channel_id);
              console.log('=================================');
            });
          });
        });

        f.once('error', function(err) {
          logger.info('获取邮件信息出错:'+err);
          console.error('获取邮件信息出错:', err);
        });

        f.once('end', function() {
          console.log('所有新邮件的信息已获取完毕');
        });
      });
    });
  });

  imap.once('error', function(err) {
    logger.info('IMAP 错误:'+err);
    console.error('IMAP 错误:', err);
  });

  imap.once('end', function() {
    console.log('与 IMAP 服务器的连接已关闭');
  });

  imap.connect();

}
