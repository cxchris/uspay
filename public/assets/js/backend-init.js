define(['backend'], function (Backend) {
    require.config({
	    paths: {
	        'layui': '../layui/layui',
	    },
	    shim: {
	        'layui': {
	            deps: ['css!../layui/css/layui.css'],
	            init: function () {
	                return this.layui.config({dir: '/assets/js/layui/'});
	            }
	        }
	    }
	});
});