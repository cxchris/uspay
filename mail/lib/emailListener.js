import Imap from 'imap';
import log4js from './log4.js';

//通知地址
// const url = 'https://onepayus.com/api/upnotice/callback';
const url = 'http://localhost:1818/api/upnotice/callback';

function startEmailListener(config,fileName) {
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
              console.log(parsed.from)
              console.log(parsed.subject);

              //解读from和subject，1，from符合是cash app，2，subject截取金额和备注(for后面的是备注)

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

export default startEmailListener;
