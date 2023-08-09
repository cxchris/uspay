// db.js
import mysql from 'mysql';

const dbConfig = {
  host: '127.0.0.1',
  user: 'root',
  password: 'root',
  database: 'uspay',
};

const pool = mysql.createPool(dbConfig);

export default pool
