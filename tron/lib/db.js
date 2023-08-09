import mysql from 'mysql';
import util from 'util';

const dbConfig = {
  host: process.env.host,
  user: process.env.user,
  password: process.env.password,
  database: process.env.database,
};

const pool = mysql.createPool(dbConfig);
const getConnectionAsync = util.promisify(pool.getConnection).bind(pool);
const queryAsync = util.promisify(pool.query).bind(pool);


/**
 * 向数据库表中插入数据
 * @param {string} table - 要插入数据的数据库表名
 * @param {object} data - 包含要插入的数据的对象，格式为 { key1: value1, key2: value2, ... }
 * @returns {Promise<object>} - 返回一个 Promise 对象，表示插入操作的结果
 */
const insert = async (table,data) => {
  let connection;
  try {
    connection = await getConnectionAsync();
    const keys = Object.keys(data).join(', ');
    const placeholders = Object.values(data).map(() => '?').join(', ');

    const query = `INSERT INTO ${table} (${keys}) VALUES (${placeholders})`;
    const values = Object.values(data);
    const result = await queryAsync(query, values);
    // console.log('数据插入成功:', result);
    return result;
  } catch (err) {
    console.error('执行sql时出错:', err);
    throw err;
  } finally {
    if (connection) {
      connection.release();
      // pool.end();
    }
  }
}

export { insert }