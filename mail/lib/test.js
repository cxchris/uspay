import send from './send.js';
const data = {
	from:'Cash App <cash@square.com>',
	subject:'Bay lost sent you $1 for dtuZReiH',
	containsContinue:0,
	containsReceived:1
}

send('test',data);