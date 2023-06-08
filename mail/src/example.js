import startEmailListener from './emailListener.js';
import fs from 'fs';
import getfilename from './getfilename.js';

const currentFileUrl = import.meta.url;
const fileName = getfilename(currentFileUrl);
// console.log(fileName)


fs.readFile('config/'+fileName+'.json', 'utf8', (err, data) => {
  if (err) {
    console.error('读取文件'+'config/'+fileName+'.json'+'时出错:', err);
    return;
  }

  const config = JSON.parse(data);
  console.log('配置文件内容:', config);
  startEmailListener(config);
});