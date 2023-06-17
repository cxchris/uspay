import md5 from 'md5';

export function getSign (params, key = '') {
	const sortedParams = Object.fromEntries(Object.entries(params).sort());
	let str = '';
	for (let [paramKey, paramValue] of Object.entries(sortedParams)) {
		if(paramValue !== ''){
			str += encodeURIComponent(paramKey) + '=' + encodeURIComponent(paramValue) + '&';
		}
	}
	str += 'key=' + key;

	str = decodeURIComponent(str);
	// console.log(str)

	const res = md5(str).toUpperCase(); // Assuming you have an md5 hashing function
	// console.log(res)
	return res;
}