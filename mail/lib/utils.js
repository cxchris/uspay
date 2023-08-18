import md5 from 'md5';

/**
 * 成功响应函数
 * @param {any} data - 成功时要返回的数据，可以是任意类型
 * @param {string} msg - 响应消息，可选参数，默认为 'Success' 表示成功
 * @param {number} code - 响应状态码，可选参数，默认为 200 表示成功
 * @returns {Object} - 包含成功响应信息的对象
 * @property {number} code - 响应状态码，根据传入的 code 参数或默认为 200 表示成功
 * @property {string} msg - 响应消息，根据传入的 msg 参数或默认为 'Success' 表示成功
 * @property {number} time - 当前时间戳，精确到秒
 * @property {any} data - 成功时返回的数据，可以是任意类型，默认为空数组
 */
export const success = (data,msg,code) => {
  const result = {
    code: code || 200,
    msg: msg || 'Success',
    time: Math.floor(Date.now() / 1000), // 当前时间戳，精确到秒
    data: data || []
  };

  return result;
}

/**
 * 创建一个错误信息对象。
 *
 * @param {string} msg - 错误信息文本。
 * @param {number} code - 错误码（可选，默认为0）。
 * @returns {object} - 包含错误信息的对象。
 * 
 * @example
 * const result = error('发生错误', 500);
 * console.log(result);
 * // Output:
 * // {
 * //   code: 500,
 * //   msg: '发生错误',
 * //   time: 1630486157, // 当前时间戳，精确到秒
 * //   data: []
 * // }
 */
export const error = (msg,code) => {
  const result = {
    code: code || 0,
    msg: msg || 'Error',
    time: Math.floor(Date.now() / 1000), // 当前时间戳，精确到秒
    data: []
  };

  return result;
}


// 成功数据中间件
export const successMiddleware = (req, res, next) => {
  res.success = (data, msg, code) => {
    const successResponse = success(data, msg, code);
    res.json(successResponse);
  };
  next();
};

// 错误数据中间件
export const errorMiddleware = (req, res, next) => {
  res.error = (msg, code) => {
    const errorResponse = error(msg,code);
    res.json(errorResponse);
  };
  next();
};

//获取签名
export const getSign = (params, key = '') => {
  const sortedParams = Object.fromEntries(Object.entries(params).sort());
  let str = '';
  for (let [paramKey, paramValue] of Object.entries(sortedParams)) {
    str += encodeURIComponent(paramKey) + '=' + encodeURIComponent(paramValue) + '&';
  }
  str += 'key=' + key;

  str = decodeURIComponent(str);
  // console.log(str)

  const res = md5(str).toUpperCase(); // Assuming you have an md5 hashing function
  // console.log(res)
  return res;
};

// 验证签名
export const verifySign = (params, key = '') => {
  const sign = params.sign;
  delete params.sign;

  const sys_sign = getSign(params, key);

  return sys_sign === sign;
};

//table转json
export const tojson = (input) => {
  const trimmedString = input.trim();
  const lines = trimmedString.split('\n');
  const headers = lines[1].split('│').map(header => header.trim()).filter(header => header.length > 0);
  const tableData = [];

  for (let i = 3; i < lines.length - 1; i++) {
    const cells = lines[i].split('│').map(cell => cell.trim()).filter(cell => cell.length > 0);
    const rowData = {};

    for (let j = 0; j < headers.length; j++) {
      rowData[headers[j]] = cells[j];
    }

    tableData.push(rowData);
  }

  const jsonData = JSON.stringify(tableData, null, 2);
  return jsonData;
}