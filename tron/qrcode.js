import QRCode from 'qrcode';
const tronAddress = 'TJJCmDFkbqrUhQKXwTo1gMQWLnYkBHi3yd';

// 生成Tron地址二维码
QRCode.toDataURL(tronAddress, (error, url) => {
  if (error) {
    console.error('生成二维码时出错:', error);
    return;
  }

  // 输出二维码URL
  console.log(url);
});
