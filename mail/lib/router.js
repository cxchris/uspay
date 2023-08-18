import express from 'express';
import { list, start, stop } from '../api/pm2.js'; // 调整导入路径
const router = express.Router();


const routers = [
  { method: 'POST', route: '/pm2/list', handler: list },  //查找列表
  { method: 'POST', route: '/pm2/start', handler: start },  //开始
  { method: 'POST', route: '/pm2/stop', handler: stop },  //停止
];


// 将 list 数组中的路由信息注册到 Express 路由中
if(routers){
	routers.forEach(({ method, route, handler }) => {
		if(method){
			router[method.toLowerCase()](route, handler);
		}
	});
}

export default router;
