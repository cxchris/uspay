// import TronWeb from 'tronweb';
// import dotenv from 'dotenv';
// dotenv.config();

// const HttpProvider = TronWeb.providers.HttpProvider;

// const fullNode = new HttpProvider('https://api.trongrid.io');
// const solidityNode = new HttpProvider('https://api.trongrid.io');
// const eventServer = new HttpProvider('https://api.trongrid.io');

// const privateKey = process.env.privatekey;

// const tronWeb = new TronWeb(fullNode, solidityNode, eventServer, privateKey);

// async function getAccountTransactions() {
//     const address = 'TUkESKhFR3pisKsPvryGkTpBMKBx7pNS29'; // 替换为要查询的 TRON 钱包地址
//     const transactions = await tronWeb.trx.getTransactionsRelated(address);

//     console.log('交易信息:', transactions);
// }

// getAccountTransactions();

import axios from 'axios';
import dotenv from 'dotenv';
dotenv.config();

const address = 'TUVVB3tkotrKW8xiYTwSTiCdhASEHj2Rza';

const url = `https://api.trongrid.io/v1/accounts/${address}/transactions/trc20`;
const params = {
    limit: 10,
    contract_address: process.env.trc20ContractAddress
};

axios.get(url, { params })
    .then(response => {
        const transactions = response.data.data;
        console.log('交易记录:', transactions);
    })
    .catch(error => {
        console.error('请求失败:', error);
    });