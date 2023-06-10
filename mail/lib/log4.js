import log4js from 'log4js';
import moment from 'moment';

log4js.configure({
  appenders: {
    app: {
      type: 'dateFile',
      filename: '../logs/' + moment().format('YYYYMM') + '/'+moment().format('DD')+'.log',
      pattern: 'yyyy-MM-dd',
      numBackups: 30,
      layout: {
        type: 'pattern',
        pattern: '%d{yyyy-MM-dd hh:mm:ss} [%p] %c - %m%n'
      }
    }
  },
  categories: {
    default: { appenders: ['app'], level: 'info' }
  }
});

// 导出 logger 变量
export default log4js;

// 记录日志
// logger.info('Hello, Log4js!');