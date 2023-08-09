// api/accout.js
import { success, error ,successMiddleware, errorMiddleware, verifySign } from '../lib/utils.js'
import TronWeb from 'tronweb';
import dotenv from 'dotenv';
dotenv.config();


const HttpProvider = TronWeb.providers.HttpProvider;

const fullNode = new HttpProvider('https://api.trongrid.io');
const solidityNode = new HttpProvider('https://api.trongrid.io');
const eventServer = new HttpProvider('https://api.trongrid.io');

const privateKey = process.env.privatekey;

const tronWeb = new TronWeb(fullNode, solidityNode, eventServer, privateKey);

const key = process.env.key; //验签key

const trc20ContractAddress = process.env.trc20ContractAddress; // USDT的合约地址

//创建账号
export const create = async (req, res) => {
  try {
    const formData = req.body;
    const name = formData.name;
    if (!name) {
      throw new Error('name cannot be empty');
    }

    // 验证签名
    const isValidSignature = verifySign(formData, key);
    if (!isValidSignature) {
      throw new Error('Invalid signature');
    }

    const account = await tronWeb.createAccount();

    const usdtAddress = account.address.base58;
    const usdtPrivateKey = account.privateKey;

    // console.log('USDT Address:', usdtAddress);
    // console.log('USDT Private Key:', usdtPrivateKey);

    const data = {usdtAddress,usdtPrivateKey}

    res.success(data);
  } catch (error) {
    res.error(error.message);
  }
};

//获取trx余额接口
export const balance = async (req, res) => {
  try {
    const formData = req.body;

    const address = formData.address;
    if (!address) {
      throw new Error('Address cannot be empty');
    }

    const tronAddressRegex = /^T[a-zA-HJ-NP-Za-km-z1-9]{33}$/;
    const isValidTronAddress = tronAddressRegex.test(address);

    if(!isValidTronAddress){
      throw new Error('Invalid Tron address');
    }

    // 验证签名
    const isValidSignature = verifySign(formData, key);
    if (!isValidSignature) {
      throw new Error('Invalid signature');
    }

    const balance = await tronWeb.trx.getBalance(address);
    const trxBalanceInTRX = tronWeb.fromSun(balance);

    const data = { trxBalanceInTRX }

    res.success(data);
    // // console.log('Account Balance:', balance);
    // console.log(trxBalanceInTRX)

  } catch (error) {
    res.error(error.message);
  }
};

//获取USDT余额接口
export const usdtbalance = async (req, res) => {
  try {
    const formData = req.body;

    const address = formData.address;
    if (!address) {
      throw new Error('Address cannot be empty');
    }

    const tronAddressRegex = /^T[a-zA-HJ-NP-Za-km-z1-9]{33}$/;
    const isValidTronAddress = tronAddressRegex.test(address);

    if(!isValidTronAddress){
      throw new Error('Invalid Tron address');
    }

    // 验证签名
    const isValidSignature = verifySign(formData, key);
    if (!isValidSignature) {
      throw new Error('Invalid signature');
    }

    let contract = await tronWeb.contract().at(trc20ContractAddress);
    //Use call to execute a pure or view smart contract method.
    // These methods do not modify the blockchain, do not cost anything to execute and are also not broadcasted to the network.
    let result = await contract.balanceOf(address).call();
    const balance = result.toNumber();

    let decimals = await contract.decimals().call();
    const balanceDecimal = balance / Math.pow(10, decimals);

    const data = { balanceDecimal }

    res.success(data);
    // console.log('USDT余额:', balanceDecimal);
  } catch(error) {
    res.error(error.message);
  }
};

//TRX转账
export const trxtransfer = async (req, res) => {
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
};

//智能合约转账
export const triggerSmartContract = async (req, res) => {
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
};
