import startEmailListener from '../lib/emailListener.js';
import fs from 'fs';
import getfilename from '../lib/getfilename.js';
import log4js from '../lib/log4.js';

const currentFileUrl = import.meta.url;
const fileName = getfilename(currentFileUrl);
const logger = log4js.getLogger(fileName+'.js');
// console.log(fileName)

fs.readFile('../config/'+fileName+'.json', 'utf8', (err, data) => {
  if (err) {
    console.error('读取文件'+'config/'+fileName+'.json'+'时出错:', err);
    return;
  }

  const config = JSON.parse(data);
  console.log('配置文件内容:', config);
  try {
    startEmailListener(config,fileName);
  } catch (err) {
    logger.error('运行mail listener出错:', err);
  }
});