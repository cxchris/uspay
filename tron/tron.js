import express from 'express'
import bodyParser from 'body-parser';
import { error, successMiddleware, errorMiddleware } from './lib/utils.js'

import router from './lib/router.js'; 

const app = express();
const port = 3001;

app.use(bodyParser.urlencoded({ extended: true }));

// 在app中应用成功数据中间件
app.use(successMiddleware);
app.use(errorMiddleware);

// 使用路由处理逻辑
app.use(router);

// 404 接口不存在的处理
app.use((req, res, next) => {
  const err = error('Not Found', 404);
  next(err); // 将错误消息传递给下一个中间件或路由处理程序
});

// 错误处理中间件
app.use((err, req, res, next) => {
  // 在这里处理错误消息，并返回JSON响应
  res.status(err.code || 500).json(err);
});

app.listen(port, () => {
  console.log(`Server is running on http://localhost:${port}`);
});