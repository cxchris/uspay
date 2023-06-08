import Imap from 'imap';

function startEmailListener(config) {
  const imap = new Imap(config);

  imap.once('ready', function() {
    imap.openBox('INBOX', true, function(err, box) {
      if (err) throw err;

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
              console.log('Subject:', parsed.subject);
              console.log('=================================');
            });
          });
        });

        f.once('error', function(err) {
          console.error('获取邮件信息出错:', err);
        });

        f.once('end', function() {
          console.log('所有新邮件的信息已获取完毕');
        });
      });
    });
  });

  imap.once('error', function(err) {
    console.error('IMAP 错误:', err);
  });

  imap.once('end', function() {
    console.log('与 IMAP 服务器的连接已关闭');
  });

  imap.connect();
}

export default startEmailListener;
