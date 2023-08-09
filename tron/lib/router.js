import express from 'express';
import { create, balance, usdtbalance, trxtransfer, triggerSmartContract } from '../api/account.js'; // 调整导入路径
const router = express.Router();


const list = [
  { method: 'POST', route: '/account/create', handler: create },  //创建账号
  { method: 'POST', route: '/account/balance', handler: balance }, //获取trx余额接口
  { method: 'POST', route: '/account/usdtbalance', handler: usdtbalance }, //获取USDT余额接口 
  { method: 'POST', route: '/account/trxtransfer', handler: trxtransfer }, //TRX转账
  { method: 'POST', route: '/account/triggerSmartContract', handler: triggerSmartContract }, //智能合约转账
];


// 将 list 数组中的路由信息注册到 Express 路由中
if(list){
	list.forEach(({ method, route, handler }) => {
		if(method){
			router[method.toLowerCase()](route, handler);
		}
	});
}

export default router;
