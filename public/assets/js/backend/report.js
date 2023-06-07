define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'report/index',
                    detail: 'report/detail',
                }
            });

            var table = $("#table");

            //在表格内容渲染完成后回调的事件
            table.on('post-body.bs.table', function (e, json) {
                $("tbody tr[data-index]", this).each(function () {
                    // if (parseInt($("td:eq(1)", this).text()) == Config.admin.id) {
                    //     $("input[type=checkbox]", this).prop("disabled", true);
                    // }
                });
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
                columns: [
                    [

                        {
                            field: 'merchant_number', 
                            title: '商户号',
                            formatter: function (value,row){
                                return row.merchant_name+'('+value+')';
                            },
                            searchList: $.getJSON("order/collection/merchantlist")
                        },
                        {field: 'sum_money', title: '交易金额',operate:false},
                        {field: 'sum_rate_money', title: '代收手续费（百分比+每笔）',operate:false},
                        {field: 'sum_account_money', title: '到账金额',operate:false},
                        
                        {field: 'create_time', title: '创建时间',  operate: 'RANGE', addclass: 'datetimerange', sortable: true,visible:false, defaultValue:Moment().startOf('day').format('YYYY-MM-DD 00:00:00') + ' - ' + Moment().endOf('day').format('YYYY-MM-DD 23:59:59')},
                        // {field: 'billing_time', title: '结算时间',  operate: 'RANGE', addclass: 'datetimerange', sortable: true,operate:false},
                        
                       
                        {
                            field: 'operate',title: '操作',table: table,
                            events: Controller.api.events.operate, 
                            formatter: Controller.api.formatter.operate,
                            buttons: [
                                {
                                    name: 'amount',
                                    text: '结算详情',
                                    icon: 'fa fa-primary',
                                    classname: 'btn btn-xs btn-info btn-amount btn-dialog',
                                    extend: 'data-toggle="tooltip" data-area=["100%","100%"]',
                                    url: 'report/detail',
                                },
                            ],
                        }
                    ]
                ]
            });

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
        detail: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'report/detail',
                }
            });
            var table = $("#history-table");

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
                columns: [
                    [
                        {field: 'orderno', title: '系统订单号'},
                        {field: 'out_trade_no', title: '商户订单号'},
                        {field: 'tn', title: '三方订单号'},
                        {field: 'channel_id', title: '代收通道',formatter: function (value,row) 
                        {
                            return row.channel_name+'('+value+')';
                        }},
                        
                        // {field: 'level', title: '层级', visible:false, searchList: {"0": '进行中', "1": '已支付',"2": "支付失败","-1": "请求失败"}},

                        {
                            field: 'merchant_number', 
                            title: '商户号',
                            formatter: function (value,row){
                                return row.merchant_name+'('+value+')';
                            },
                            searchList: $.getJSON("order/collection/merchantlist")
                        },

                        // {field: 'merchant_name', title: '商户名',formatter: function (value,row) 
                        // {
                        //     return value+'('+row.merchant_number+')';
                        // }},
                        {field: 'pay_type', title: '支付方式',operate:false},

                        {field: 'money', title: '交易金额',operate:false},
                        {field: 'collection_fee_rate', title: '费率',operate:false},
                        {field: 'rate_money', title: '代收手续费（百分比+每笔）',operate:false},
                        {field: 'account_money', title: '到账金额',operate:false},
                        
                        {field: 'create_time', title: '创建时间',  operate: 'RANGE', addclass: 'datetimerange', sortable: true},
                        {field: 'billing_time', title: '结算时间',  operate: 'RANGE', addclass: 'datetimerange', sortable: true,operate:false},
                        
                       
                        
                    ]
                ]
            });

            $("input[type='text']").each(function (index) {
                this.autocomplete = "off";
            })

            // 为表格绑定事件
            Table.api.bindevent(table);
        }
    };
    return Controller;
});
