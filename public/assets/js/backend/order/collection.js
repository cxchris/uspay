define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var group_id = Config.admin.group_id;
    var date = new Date();
    //1-admin
    if(group_id == 2){
        var Controller = {
            index: function () {
                // 初始化表格参数配置
                Table.api.init({
                    extend: {
                        index_url: 'order/collection/index',
                    }
                });
                var _this = this;

                var table = $("#table");

                //当表格数据加载完成时
                table.on('load-success.bs.table', function (e, json) {
                    // console.log(json)
                    // $("#money").text(json.extend.money);
                    // $("#total").text(json.extend.total);
                    // $("#price").text(json.extend.price);
                    // $("#tax").text(json.extend.tax);
                    // $("#rate").text(json.extend.rate);
                });

                // 初始化表格
                table.bootstrapTable({
                    url: $.fn.bootstrapTable.defaults.extend.index_url,
                    fixedColumns: true,
                    fixedNumber: 0,
                    fixedRightNumber: 0,
                    //禁用默认搜索
                    search: false,
                    //启用普通表单搜索
                    commonSearch: true,
                    //可以控制是否默认显示搜索单表,false则隐藏,默认为false
                    searchFormVisible: true,
                    exportOptions: {
                        fileName: 'export_' + date.getFullYear() + '-' + date.getMonth() + '-' + date.getDate(),
                        ignoreColumn: ['operate'], //默认不导出第一列(checkbox)与操作(operate)列
                    },
                    columns: [
                        [
                            {field: 'orderno', title: '平台订单号'},
                            {field: 'out_trade_no', title: '商户订单号',operate: 'LIKE'},
                            {field: 'eshopno', title: '电商订单',operate: 'LIKE'},
                            {field: 'tn', title: '三方订单号'},
                            {field: 'channel_id', title: '代收通道',formatter: function (value,row) 
                            {
                                return row.channel_name+'('+value+')';
                            },searchList: $.getJSON("order/collection/colselect")},
                            {
                                field: 'status_type', 
                                title: '状态(上游)',
                                operate:false,
                                formatter: Table.api.formatter.label,
                                custom: {'进行中': 'info', '下单失败': 'default','已支付':'success','支付失败':'warning'},
                            },
                            {
                                field: 'status', 
                                title: '状态(上游)',
                                visible:false,
                                searchList: Object.assign({},$.getJSON("order/collection/typeList")),
                            },

                            {field: 'merchant_number', title: '商户号',operate:false},
                            {field: 'create_time', title: '创建时间',  operate: 'RANGE', addclass: 'datetimerange', sortable: true, defaultValue:Moment().startOf('day').format('YYYY-MM-DD 00:00:00') + ' - ' + Moment().endOf('day').format('YYYY-MM-DD 23:59:59')},
                            {field: 'callback_time', title: '回调时间', operate: 'RANGE', addclass: 'datetimerange', sortable: true},
                            {field: 'money', title: '交易金额',operate:false},
                            {field: 'rate_money', title: '代收手续费（百分比+每笔）',operate:false},
                            {field: 'account_money', title: '到账金额',operate:false},
                            {field: 'billing_around', title: '结算周期',operate:false},
                            {field: 'billing_time', title: '结算时间',  operate: 'RANGE', addclass: 'datetimerange', sortable: true,operate:false},
                            {
                                field: 'is_billing', 
                                title: '结算状态', 
                                formatter: Table.api.formatter.status,
                                custom: {0: 'error', 1: 'success'},
                                searchList: {0: '未结算', 1: '已结算'}
                            },
                            {
                                field: 'notify_status_type', 
                                title: '通知状态',
                                operate:false,
                                formatter: Table.api.formatter.label,
                                custom: {'未通知': 'info', '通知成功': 'success','通知失败':'default','异常':'warning'},
                            },
                            {
                                field: 'notify_status', 
                                title: '通知状态',
                                visible:false,
                                searchList: $.getJSON("order/payment/notifyList")
                            },
                        ]
                    ],
                    // onCommonSearch: function(){
                    //     var options = table.bootstrapTable('getOptions');
                    //     var queryParams = options.queryParams;
                    //     options.queryParams = function (params) {
                    //         params = queryParams(params);

                    //         var filter = params.filter ? JSON.parse(params.filter) : {};
                    //         if(filter.check == 1){
                    //             delete(filter.check)
                    //         }

                    //         params.filter = JSON.stringify(filter);

                    //         return params;
                    //     };
                    //     return false;
                    // }
                });
                
                $("input[type='text']").each(function (index) {
                    this.autocomplete = "off";
                })
                

                // 为表格绑定事件
                Table.api.bindevent(table);
            },
            add: function () {
                Form.api.bindevent($("form[role=form]"));
            },
            edit: function () {
                Form.api.bindevent($("form[role=form]"));
            },
        };
        return Controller;
    }else{
        var Controller = {
            index: function () {
                // 初始化表格参数配置
                Table.api.init({
                    extend: {
                        index_url: 'order/collection/index',
                    }
                });
                var _this = this;

                var table = $("#table");

                //当表格数据加载完成时
                table.on('load-success.bs.table', function (e, json) {
                    // console.log(json)
                    $("#money").text(json.extend.money);
                    $("#total").text(json.extend.total);
                    $("#success_total").text(json.extend.success_total);
                    $("#price").text(json.extend.price);
                    $("#tax").text(json.extend.tax);
                    $("#rate").text(json.extend.rate);
                });

                table.on('post-body.bs.table', function (e, settings, json, xhr) {
                    $(".btn-amount").data("end", function(){
                        //关闭后的事件
                        $('.btn-refresh').trigger('click')
                    });
                });

                // 初始化表格
                table.bootstrapTable({
                    url: $.fn.bootstrapTable.defaults.extend.index_url,
                    fixedColumns: true,
                    fixedNumber: 0,
                    fixedRightNumber: 1,
                    //禁用默认搜索
                    search: false,
                    //启用普通表单搜索
                    commonSearch: true,
                    //可以控制是否默认显示搜索单表,false则隐藏,默认为false
                    searchFormVisible: true,
                    // exportTypes: ['csv', 'excel'],
                    exportOptions: {
                        fileName: 'export_' + date.getFullYear() + '-' + date.getMonth() + '-' + date.getDate(),
                        ignoreColumn: [ 'operate'], //默认不导出第一列(checkbox)与操作(operate)列
                    },
                    columns: [
                        [
                            {field: 'orderno', title: '平台订单号'},
                            {field: 'out_trade_no', title: '商户订单号',operate: 'LIKE'},
                            {field: 'eshopno', title: '电商订单',operate: 'LIKE'},
                            {field: 'tn', title: '三方订单号'},
                            {field: 'channel_id', title: '代收通道',formatter: function (value,row) 
                            {
                                return row.channel_name+'('+value+')';
                            },searchList: $.getJSON("order/collection/colselect")},
                            
                            {
                                field: 'status_type', 
                                title: '状态(上游)',
                                operate:false,
                                formatter: Table.api.formatter.label,
                                custom: {'进行中': 'info', '下单失败': 'default','已支付':'success','支付失败':'warning'},

                            },
                            {
                                field: 'status', 
                                title: '状态(上游)',
                                visible:false,
                                searchList: Object.assign({},$.getJSON("order/collection/typeList")),

                            },

                            {
                                field: 'merchant_number', 
                                title: '商户号',
                                formatter: function (value,row){
                                    return row.merchant_name+'('+value+')';
                                },
                                searchList: $.getJSON("order/collection/merchantlist")
                            },
                            {field: 'pay_type', title: '支付方式',operate:false},
                            {field: 'create_time', title: '创建时间',  operate: 'RANGE', addclass: 'datetimerange', sortable: true, defaultValue:Moment().startOf('day').format('YYYY-MM-DD 00:00:00') + ' - ' + Moment().endOf('day').format('YYYY-MM-DD 23:59:59')},
                            {field: 'callback_time', title: '回调时间', operate: 'RANGE', addclass: 'datetimerange', sortable: true},
                            {field: 'money', title: '交易金额',operate:false},
                            {field: 'virtual_money', title: '优惠金额',operate:false},
                            {field: 'rate_money', title: '代收手续费（百分比+每笔）',operate:false},
                            {field: 'rate_t_money', title: '三方手续费',operate:false},
                            {field: 'account_money', title: '到账金额',operate:false},
                            {field: 'billing_around', title: '结算周期',operate:false},
                            {field: 'billing_time', title: '结算时间',  operate: 'RANGE', addclass: 'datetimerange', sortable: true,operate:false},
                            {field: 'utr', title: 'utr'},
                            {field: 'txtUTR', title: 'txtUTR'},
                            {
                                field: 'is_billing', 
                                title: '结算状态', 
                                formatter: Table.api.formatter.status,
                                custom: {0: 'error', 1: 'success'},
                                searchList: {0: '未结算', 1: '已结算'}
                            },
                            {
                                field: 'notify_status_type', 
                                title: '通知状态',
                                operate:false,
                                formatter: Table.api.formatter.label,
                                custom: {'未通知': 'info', '通知成功': 'success','通知失败':'default','异常':'warning'},
                            },
                            {
                                field: 'notify_status', 
                                title: '通知状态',
                                visible:false,
                                searchList: $.getJSON("order/collection/notifyList")
                            },

                            {field: 'notify_number', title: '通知次数',operate:false},
                            {field: 'update_time', title: '通知时间',operate:false},
                            {field: 'notify_url', title: '异步通知地址',operate:false},
                            {field: 'request_ip', title: '请求IP',operate:false},
                            {
                                field: 'operate',title: '操作',table: table,
                                events: Controller.api.events.operate, 
                                formatter: Controller.api.formatter.operate,
                                buttons: [
                                    {
                                        name: 'amount',
                                        text: '查询',
                                        icon: 'fa fa-primary',
                                        classname: 'btn btn-xs btn-info btn-amount btn-dialog',
                                        extend: 'data-toggle="tooltip" data-area=["100%","100%"]',
                                        url: 'order/collection/detail',
                                    },
                                    {
                                        name: 'notice',
                                        text: '通知',
                                        icon: 'fa fa-primary',
                                        classname: 'btn btn-info btn-xs btn-notice',
                                    },
                                    // {
                                    //     name: 'detail',
                                    //     text: '订单明细',
                                    //     icon: 'fa fa-primary',
                                    //     classname: 'btn btn-info btn-xs btn-detail btn-dialog',
                                    //     extend: 'data-toggle="tooltip" data-area=["60%","50%"]',
                                    //     url: 'order/collection/orderdetail',
                                    // },
                                    {
                                        name: 'update',
                                        text: '状态修改',
                                        // icon: 'fa fa-warning',
                                        classname: 'btn btn-warning btn-xs btn-update',
                                    },
                                ],
                            }
                        ]
                    ],
                    // onCommonSearch: function(){
                    //     var options = table.bootstrapTable('getOptions');
                    //     var queryParams = options.queryParams;
                    //     options.queryParams = function (params) {
                    //         params = queryParams(params);

                    //         var filter = params.filter ? JSON.parse(params.filter) : {};
                    //         if(filter.check == 1){
                    //             delete(filter.check)
                    //         }

                    //         params.filter = JSON.stringify(filter);

                    //         return params;
                    //     };
                    //     return false;
                    // }
                });
                
               

                //结算
                $('.btn-check').click(function(){
                    $.ajax({
                        type:"POST",
                        url:"order/collection/channelselect",
                        data:{
                            
                        },
                        dataType:"json",
                        success:function (data) {
                            layer.open({
                                type: 1,
                                skin: 'layui-layer-demo', //样式类名
                                area: ['400px', '300px'], //宽高
                                closeBtn: 1, //不显示关闭按钮
                                anim: 2,
                                title:'结算订单',
                                shadeClose: true, //开启遮罩关闭
                                content: _this.innerhtml(data),
                                zIndex:99
                            });

                            Form.api.bindevent($("#check"));
                            _this.checkpost();

                        }
                    })
                })

                // setInterval(function (){
                //     table.bootstrapTable('refresh',{silent: true });
                //     Layer.msg('刷新成功');

                // },1000*10);

                $("input[type='text']").each(function (index) {
                    this.autocomplete = "off";
                })

                // 为表格绑定事件
                Table.api.bindevent(table);
            },
            api:{
                bindevent: function () {
                    Form.api.bindevent($("form[role=form]"));
                },
                events: {
                    operate: {
                        'click .btn-notice': function (e, value,row,index) {
                            var that = this;
                            var table = $(that).closest('table'); 
                            var options = table.bootstrapTable('getOptions');
                            var load = Layer.confirm('是否给下游商户重新发起一次回调', function (text, index) {
                                if($.trim(text)==''){
                                    Layer.msg('不能为空');
                                    return false
                                }

                                var ids = row['id'];

                                $.ajax({
                                    type:"POST",
                                    url:"order/collection/callback",
                                    data:{
                                        id:ids,
                                    },
                                    dataType:"json",
                                    success:function (data) {
                                        console.log(data);
                                        // layer.close(load)
                                        if(data.code == 1){
                                            Layer.msg(data.msg)
                                            // Layer.closeAll()
                                            $('.btn-refresh').trigger('click')
                                        }else{
                                            Layer.alert(data.msg, {icon: 5})
                                            $('.btn-refresh').trigger('click')
                                        }

                                    }
                                })
                            })
                        },

                        'click .btn-update': function (e, value,row,index) {
                            var that = this;
                            var table = $(that).closest('table'); 
                            var options = table.bootstrapTable('getOptions');
                            // console.log(row)

                            var content = '<form id="check"><div style="padding: 30px;" class="table-update menulist">\
                                    <div class="form-group">\
                                        <label>状态:</label>\
                                        <select id="check_status" data-rule="required" class="form-control selectpicker" name="check_status">\
                                        ';
                                
                                    content += '<option value="1" >已支付</option>';
                                    content += '<option value="2" >支付失败</option>';
                                
                                content +=  '</select>\
                                    </div>\
                                    <button type="submit" class="btn btn-success check_status_submit" formnovalidate="">提交</button>\
                                </div></form>';

                            layer.open({
                                type: 1,
                                skin: 'layui-layer-demo', //样式类名
                                area: ['300px', '240px'], //宽高
                                closeBtn: 1, //不显示关闭按钮
                                anim: 2,
                                title:'状态修改',
                                shadeClose: true, //开启遮罩关闭
                                content: content,
                                zIndex:99,
                                success: function(){

                                }
                            });

                            $('.check_status_submit').click(function(){
                                var status = $('#check_status').val();
                                console.log(status)

                                $.ajax({
                                    type:"POST",
                                    url:"order/collection/updatestatus",
                                    data:{
                                        status:status,
                                        id:row.id,
                                    },
                                    dataType:"json",
                                    success:function (data) {
                                        // console.log(data);
                                        // layer.close(load)
                                        if(data.code == 1){
                                            Layer.msg(data.msg)
                                            Layer.closeAll()
                                            $('.btn-refresh').trigger('click')
                                        }else{
                                            Layer.alert(data.msg, {icon: 5})
                                            // $('.btn-refresh').trigger('click')
                                        }

                                    }
                                })
                            })
                        },
                    }
                },
                formatter: {
                    operate: function (value, row, index) {
                        var table = this.table;
                        // 操作配置
                        var options = table ? table.bootstrapTable('getOptions') : {};
                        // 默认按钮组
                        var buttons = $.extend([], this.buttons || []);
                        // 所有按钮名称
                        var names = [];
                        buttons.forEach(function (item) {
                            names.push(item.name);
                        });
                        if (options.extend.dragsort_url !== '' && names.indexOf('dragsort') === -1) {
                            buttons.push({
                                name: 'dragsort',
                                icon: 'fa fa-arrows',
                                title: __('Drag to sort'),
                                extend: 'data-toggle="tooltip"',
                                classname: 'btn btn-xs btn-primary btn-dragsort'
                            });
                        }
                        if (options.extend.edit_url !== '' && names.indexOf('edit') === -1) {
                            buttons.push({
                                name: 'edit',
                                icon: 'fa fa-pencil',
                                title: __('Edit'),
                                extend: 'data-toggle="tooltip" data-area=["100%","100%"]',
                                classname: 'btn btn-xs btn-success btn-dialog ',
                                url: options.extend.edit_url
                            });
                        }

                        return Table.api.buttonlink(this, buttons, value, row, index, 'operate');
                    }
                }
            },
            add: function () {
                Form.api.bindevent($("form[role=form]"));
            },
            edit: function () {
                Form.api.bindevent($("form[role=form]"));
            },

            innerhtml: function(data){
                var time = Config.yesterday;
                var channel = data.channel;
                var merchant = data.merchant;

                // <div class="form-group">\
                //             <label>通道选择:</label>\
                //             <select id="check_channel_id" data-rule="required" class="form-control selectpicker" name="check_channel_id">\
                //             <option value="0" >选择</option>';
                //     for (var i=0; i < channel.length; i++){
                //         content += '<option value="'+channel[i].id+'" >'+channel[i].channel_name+'</option>';
                //     }
                //     content +=  '</select>\
                //         </div>\

                var content = '<form id="check"><div style="padding: 30px;" class="table-update menulist">\
                        <div class="form-group">\
                            <label>商户选择:</label>\
                            <select id="check_merchant_id" data-rule="required" class="form-control selectpicker" name="check_merchant_id">\
                            <option value="0" >选择</option>';
                    for (var i=0; i < merchant.length; i++){
                        content += '<option value="'+merchant[i].id+'" >'+merchant[i].merchant_name+'('+merchant[i].merchant_number+')</option>';
                    }
                    content +=  '</select>\
                        </div>\
                        <div class="form-group">\
                            <label>订单时间:</label>\
                            <input type="text" class="form-control datetimerange" name="check_create_time" autocomplete="off" value="'+time+'"  placeholder="创建时间" id="check_create_time" data-index="7">\
                        </div>\
                        <button type="submit" class="btn btn-success check_submit" formnovalidate="">提交</button>\
                    </div></form>';
                return content;

            },


            //提交
            checkpost: function(){
                var _this = this;
                $('.check_submit').click(function(){
                    // var check_channel_id = $('#check_channel_id').val();
                    var check_merchant_id = $('#check_merchant_id').val();
                    var check_create_time = $('#check_create_time').val();
                    $.ajax({
                        type:"POST",
                        url:"order/collection/check",
                        data:{
                            // check_channel_id:check_channel_id,
                            check_create_time:check_create_time,
                            check_merchant_id:check_merchant_id
                        },
                        dataType:"json",
                        success:function (data) {
                            // console.log(data);
                            // layer.close(load)
                            if(data.code == 1){
                                Layer.msg(data.msg)
                                Layer.closeAll()
                                $('.btn-refresh').trigger('click')
                            }else{
                                Layer.alert(data.msg, {icon: 5})
                                // $('.btn-refresh').trigger('click')
                            }

                        }
                    })
                })
            }
        };
        return Controller;
    }
    
});
