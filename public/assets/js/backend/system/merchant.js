define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'system/merchant/index',
                    add_url: 'system/merchant/add',
                    edit_url: 'system/merchant/edit',
                    // del_url: 'system/merchant/del',
                    amount_url: 'system/merchant/amount_edit',

                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                fixedColumns: true,
                fixedNumber: 2,
                fixedRightNumber: 1,
                columns: [
                    [
                        // { checkbox: true, },
                        {field: 'merchant_name', title: '商户名称'},
                        {field: 'merchant_number', title: '商户号'},
                        {field: 'merchant_key', title: '商户密钥'},
                        // {field: 'merchant_agent_id', title: '代理号'},
                        {field: 'merchant_amount', title: '代收余额'},
                        {field: 'merchant_payment_amount', title: '代付余额'},
                        {field: 'merchant_freeze_amount', title: '账户未结算金额'},
                        {field: 'merchant_billing_around', title: '结算周期'},
                        {field: 'status', title: __("Status"), searchList: {"1":__('Normal'),"0":'禁用'}, formatter: Table.api.formatter.status},
                        {field: 'collection_fee_rate', title: '代收手续费（百分之几+每笔）'},
                        {field: 'collection_limit', title: '代收限额'},
                        
                        {field: 'collection_status', title: '代收状态', searchList: {"1":__('Normal'),"0":'禁用'}, formatter: Table.api.formatter.status},
                        {field: 'collection_channel_name', title: '代收通道'},
                        // {field: 'today_collection_money', title: '今日代收成功金额'},
                        {field: 'payment_fee_rate', title: '代付手续费（百分之几+每笔）'},
                        {field: 'payment_limit', title: '代付限额'},

                        {field: 'payment_status', title: '代付状态', searchList: {"1":__('Normal'),"0":'禁用'}, formatter: Table.api.formatter.status},
                        {field: 'payment_channel_name', title: '代付通道'},
                        {field: 'use_ip', title: '报备IP'},
                        {field: 'create_time', title: '创建时间', formatter: Table.api.formatter.datetime, operate: 'RANGE', addclass: 'datetimerange', sortable: true},
                        {
                            field: 'operate',title: '操作',table: table,
                            events: Controller.api.events.operate, 
                            formatter: Controller.api.formatter.operate,
                            buttons: [
                                {
                                    name: 'amount',
                                    text: '余额管理',
                                    icon: 'fa fa-primary',
                                    classname: 'btn btn-xs btn-info btn-amount btn-dialog',
                                    extend: 'data-toggle="tooltip" data-area=["100%","100%"]',
                                    url: 'system/merchant/amount_edit',
                                },
                                {
                                    name: 'reset',
                                    text: '重置密码',
                                    icon: 'fa fa-primary',
                                    classname: 'btn btn-info btn-xs btn-detail btn-passReset',
                                },
                                {
                                    name: 'reset',
                                    text: '重置密钥',
                                    icon: 'fa fa-primary',
                                    classname: 'btn btn-info btn-xs btn-detail btn-googleReset',
                                },
                                {
                                    name: 'google',
                                    text: '谷歌身份验证器',
                                    icon: 'fa fa-primary',
                                    classname: 'btn btn-info btn-xs btn-detail btn-googleVaild',
                                    
                                },
                            ],
                        }
                    ]
                ],
            });

            // 为表格绑定事件
            Table.api.bindevent(table);

        },
        api:{
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            },
            events: {
                operate: {
                    'click .btn-googleVaild': function (e, value,row,index) {
                        var that = this;
                        var table = $(that).closest('table'); 
                        var options = table.bootstrapTable('getOptions');
                        var load = Layer.prompt({title: 'Google Checksum', shadeClose: true}, function (text, index) {
                            if($.trim(text)==''){
                                Layer.msg('不能为空');
                                return false
                            }

                            var ids = row['admin_id'];
                            var Checksum = $.trim(text);

                            $.ajax({
                                type:"POST",
                                url:"google/get",
                                data:{
                                    id:ids,
                                    Checksum:Checksum
                                },
                                dataType:"json",
                                success:function (data) {
                                    // console.log(data);
                                    layer.close(load)
                                    if(data.code == 1){
                                        Layer.alert(data.data.Checksum)
                                        // Layer.closeAll()
                                        // $('.btn-refresh').trigger('click')
                                    }else{
                                        Layer.alert(data.msg)
                                    }

                                }
                            })
                        })
                    },
                    'click .btn-googleReset': function (e, value,row,index) {
                        var that = this;
                        var table = $(that).closest('table'); 
                        var options = table.bootstrapTable('getOptions');
                        var load = Layer.prompt({title: 'Google Checksum', shadeClose: true}, function (text, index) {
                            if($.trim(text)==''){
                                Layer.msg('不能为空');
                                return false
                            }

                            var ids = row['admin_id'];
                            var Checksum = $.trim(text);

                            $.ajax({
                                type:"POST",
                                url:"google/reset",
                                data:{
                                    id:ids,
                                    Checksum:Checksum
                                },
                                dataType:"json",
                                success:function (data) {
                                    // console.log(data);
                                    layer.close(load)
                                    if(data.code == 1){
                                        Layer.alert(data.data.Checksum)
                                        // Layer.closeAll()
                                        // $('.btn-refresh').trigger('click')
                                    }else{
                                        Layer.alert(data.msg)
                                    }

                                }
                            })
                        })
                    },
                    'click .btn-passReset': function (e, value,row,index) {
                        var that = this;
                        var table = $(that).closest('table'); 
                        var options = table.bootstrapTable('getOptions');
                        var load = Layer.prompt({title: 'Google Checksum', shadeClose: true}, function (text, index) {
                            if($.trim(text)==''){
                                Layer.msg('不能为空');
                                return false
                            }

                            var ids = row['admin_id'];
                            var Checksum = $.trim(text);

                            $.ajax({
                                type:"POST",
                                url:"system/merchant/reset",
                                data:{
                                    id:ids,
                                    Checksum:Checksum
                                },
                                dataType:"json",
                                success:function (data) {
                                    layer.close(load)
                                    // console.log(data);
                                    if(data.code == 1){
                                        Layer.msg(data.msg,function(){
                                            Layer.closeAll()
                                        })
                                        // Layer.closeAll()
                                        // $('.btn-refresh').trigger('click')
                                    }else{
                                        Layer.alert(data.msg)
                                    }

                                }
                            })
                        })
                    }
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
            Controller.api.bindevent($("form[role=form]"));
        },
        edit: function () {
            Controller.api.bindevent($("form[role=form]"));
        },
        amount_edit: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'system/merchant/order',
                }
            });
            var table = $("#history-table");

            // 初始化表格
            table.bootstrapTable({
                url: 'system/merchant/order',
                commonSearch: false,
                visible: false,
                showToggle: false,
                showColumns: false,
                search:false,
                showExport: false,
                // showRefresh: true,
                columns: [
                    [
                        {field: 'merchant_number', title: '商户号'},
                        {field: 'money', title: '交易金额'},
                        {field: 'note', title: '备注'},
                        {field: 'type_name', title: '操作类型'},
                        {field: 'status', title: __("Status"), searchList: {"1":'成功',"0":'失败'}, formatter: Table.api.formatter.status},
                        {field: 'create_time', title: '创建时间', formatter: Table.api.formatter.datetime, operate: 'RANGE', addclass: 'datetimerange', sortable: true},
                        {field: 'admin_name', title: '操作员'},
                    ]
                ],
            });


            // 为表格绑定事件
            Table.api.bindevent(table);

            Form.api.bindevent($("form[role=form]"), function(data, ret){
                //如果我们需要在提交表单成功后做跳转，可以在此使用location.href="链接";进行跳转
                // console.log(data)
                // console.log(ret)
                var idname = '#'+ret.data.field;
                $(idname).val(ret.data.aft_money);
                Layer.alert(ret.msg)
                table.bootstrapTable('refresh', []);
                return false;
            }, function(data, ret){
                // Layer.alert('失败')
            }, function(success, error){
                //bindevent的第三个参数为提交前的回调
                //如果我们需要在表单提交前做一些数据处理，则可以在此方法处理
                //注意如果我们需要阻止表单，可以在此使用return false;即可
                //如果我们处理完成需要再次提交表单则可以使用submit提交,如下
                Form.api.submit(this, success, error);
                return false;
            });
            // Controller.api.bindevent($("form[role=form]"));

            // $('.btn-refresh').trigger('click')
        },
    };
    return Controller;
});
