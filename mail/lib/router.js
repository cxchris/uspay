import express from 'express';
import { list } from '../api/pm2.js'; // 调整导入路径
const router = express.Router();


const routers = [
  { method: 'POST', route: '/pm2/list', handler: list },  //查找列表
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
