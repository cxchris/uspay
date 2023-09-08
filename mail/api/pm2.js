// api/pm2.js
import { success, error ,successMiddleware, errorMiddleware, verifySign, tojson } from '../lib/utils.js'
import { execSync, spawnSync } from 'child_process';
import dotenv from 'dotenv';
import { fileURLToPath } from 'url';
import { dirname, resolve } from 'path';
dotenv.config();

let instruck;
if (process.platform === 'win32') {
  // windows
  instruck = 'pm2';
} else {
  // linux
  instruck = '/root/.nvm/versions/node/v15.0.1/bin/pm2';
}
const encod = 'utf-8'
const key = process.env.key; //验签key

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);
const Dir = resolve(__dirname, '..');
const tronDir = resolve(Dir, '..');
console.log(tronDir)

//获取pm list
export const list = async (req, res) => {
  try {
    const command = instruck + ' list'
    const output = execSync( command, { encoding: encod });
    const json = tojson(output);
    const data = JSON.parse(json)
    res.success(data);
  } catch (error) {
    console.log(error.message)
    res.error(error.message);
  }
};

//start pm2
export const start = (req, res) => {
  try {
    const formData = req.body;
    const id = formData.id; //传入的id
    const type = formData.type; //传入的id
    if (!id) {
      throw new Error('id cannot be empty');
    }
    if (!type) {
      throw new Error('type cannot be empty');
    }

    // 验证签名
    const isValidSignature = verifySign(formData, key);
    if (!isValidSignature) {
      throw new Error('Invalid signature');
    }

    let path;
    if (type == 'mail') {
      path = Dir+'/src/';
    } else {
      path = tronDir+'/tron/';
    }
    // console.log(path)
    const command = instruck + ' start '+path+id+'.js --name="'+id+'"';

    const output = execSync( command , { encoding: encod });

    // console.log(output)
    // const json = tojson(output);
    const data = { id }
    res.success(data);
  } catch (error) {
    console.log(error.message)
    res.error(error.message);
  }
}

//close pm2
export const stop = (req, res) => {
  try {
    const formData = req.body;
    const id = formData.id; //传入的
    if (!id) {
      throw new Error('id cannot be empty');
    }

    // 验证签名
    const isValidSignature = verifySign(formData, key);
    if (!isValidSignature) {
      throw new Error('Invalid signature');
    }

    const command = instruck +' stop '+id;

    // console.log(command)
    const output = execSync( command , { encoding: encod });
    // console.log(3333)
    // console.log(output)
    // const json = tojson(output);
    const data = { id }
    res.success(data);
  } catch (error) {
    console.log(error.message)
    res.error(error.message);
  }
}
