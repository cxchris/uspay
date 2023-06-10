import express from 'express';

const app = express();

// 定义路由和处理程序
app.get('/', (req, res) => {
  res.send('Hello, World!');
});

// 启动服务器
const port = 3000;
app.listen(port, () => {
  console.log('当前进程 ID:', process.pid);
  console.log(`Server is running on port ${port}`);
});
