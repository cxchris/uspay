import log4js from '../lib/log4.js';

const logger = log4js.getLogger('index.js');
let heartbeatCount = 1;

// 心跳回调函数
const heartbeatCallback = () => {
  logger.info('心跳:', heartbeatCount);
  heartbeatCount++;
};

// 每5秒钟写入日志
setInterval(heartbeatCallback, 5000);
