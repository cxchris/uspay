// api/pm2.js
import { success, error ,successMiddleware, errorMiddleware, verifySign, tojson } from '../lib/utils.js'
import { execSync } from 'child_process';
import dotenv from 'dotenv';
dotenv.config();

//获取pm list
export const list = async (req, res) => {
  try {
    const output = execSync('pm2 list', { encoding: 'utf-8' });
    const json = tojson(output);
    const data = JSON.parse(json)
    res.success(data);
  } catch (error) {
    res.error(error.message);
  }
};