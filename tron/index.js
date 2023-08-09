import TronWeb from 'tronweb';
import { ethers } from 'ethers';
import dotenv from 'dotenv';
dotenv.config();

/*const INFURA_API_KEY = '0c31f8f309154b8fa7665029935dd3c4';
// const ethprivateKey = '6d4f6a5ff1841868112ca45b97c10317da7f7f1019eb743759e8a24af7915b9d';
// https://mainnet.infura.io/v3/0c31f8f309154b8fa7665029935dd3c4
const provider = new ethers.providers.InfuraProvider('mainnet', INFURA_API_KEY);

async function getAddressBalance(address) {
  const balance = await provider.getBalance(address);
  return ethers.utils.formatEther(balance);
}
const address = '0x649C8331BB691c671b8d56Bb9ad0d15F67F5c7fA';
getAddressBalance(address)
  .then(balance => {
    console.log(`Address balance: ${balance} ETH`);
  })
  .catch(error => {
    console.error('Error:', error);
  });*/


const HttpProvider = TronWeb.providers.HttpProvider;

const fullNode = new HttpProvider('https://api.trongrid.io');
const solidityNode = new HttpProvider('https://api.trongrid.io');
const eventServer = new HttpProvider('https://api.trongrid.io');

const privateKey = process.env.privatekey;

const tronWeb = new TronWeb(fullNode, solidityNode, eventServer, privateKey);

//创建账号
const generateUSDTWallet = async () => {
  const account = await tronWeb.createAccount();

  const usdtAddress = account.address.base58;
  const usdtPrivateKey = account.privateKey;

  // console.log('USDT Address:', usdtAddress);
  // console.log('USDT Private Key:', usdtPrivateKey);
  return {usdtAddress,usdtPrivateKey}
};

//获取trx余额
const getAccountBalance = async (address) => {
  try {
    const balance = await tronWeb.trx.getBalance(address);
    const trxBalanceInTRX = tronWeb.fromSun(balance);
    // console.log('Account Balance:', balance);
    console.log(trxBalanceInTRX)

  } catch (error) {
    console.error('Error retrieving account balance:', error);
  }
};


//获取USDT余额
const getAddressUSDTBalance = async (address) => {
  const trc20ContractAddress = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t'; // USDT的合约地址
  try {
    // const startTime = performance.now();
    let contract = await tronWeb.contract().at(trc20ContractAddress);
    //Use call to execute a pure or view smart contract method.
    // These methods do not modify the blockchain, do not cost anything to execute and are also not broadcasted to the network.
    let result = await contract.balanceOf(address).call();
    const balance = result.toNumber();

    let decimals = await contract.decimals().call();
    const balanceDecimal = balance / Math.pow(10, decimals);

    // 结束计时
    // const endTime = performance.now();
    // const executionTime = endTime - startTime;

    console.log('USDT余额:', balanceDecimal);
    // console.log('函数执行时间:', executionTime, '毫秒');
  } catch(error) {
    console.error("trigger smart contract error",error)
  }
};

//TRX转账
async function trxtransfer(from,to,amount){
  // const privateKey = "..."; 
  var fromAddress = from; //address _from
  var toAddress = to; //address _to
  //创建一个未签名的TRX转账交易
  const tradeobj = await tronWeb.transactionBuilder.sendTrx(
        toAddress,
        amount,
        fromAddress
  );
  //签名
  const signedtxn = await tronWeb.trx.sign(
        tradeobj,
        privateKey
  );
  //广播
  const receipt = await tronWeb.trx.sendRawTransaction(
        signedtxn
  ).then(output => {
    // console.log('- Output:', output, '\n');
    return output;
  });
}


//智能合约转账
async function triggerSmartContract(address,to,amount) {
    const trc20ContractAddress = "TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t";//contract address

    try {
        let contract = await tronWeb.contract().at(trc20ContractAddress);
        //Use send to execute a non-pure or modify smart contract method on a given smart contract that modify or change values on the blockchain.
        // These methods consume resources(bandwidth and energy) to perform as the changes need to be broadcasted out to the network.
        let result = await contract.transfer(
            to, //address _to
            amount   //amount
        ).send({
            feeLimit: 100000000
        }).then(output => {
          console.log('- Output:', output, '\n');
        });
        console.log('result: ', result);
    } catch(error) {
        console.error("trigger smart contract error",error)
    }
}

export { generateUSDTWallet, trxtransfer };
// const address = 'TJJCmDFkbqrUhQKXwTo1gMQWLnYkBHi3yd';
// const to = 'TKQH11tR18Y7XGb92AmbDtQx2VAccGcz1g';
// const amount = 1000000;
// triggerSmartContract(address,to,amount);
// generateUSDTWallet();

