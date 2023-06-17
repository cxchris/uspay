import send from './send.js';
const data = {
	from:'Cash App <cash@square.com>',
	subject:'Amy Small sent you $100 for dW8bD7nL id#111283',
	containsContinue:0,
	containsReceived:1
}

send('test',data);