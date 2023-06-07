define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'record/payment/index',
                }
            });
            var _this = this;

            var table = $("#table");

            //在表格内容渲染完成后回调的事件
            table.on('post-body.bs.table', function (e, json) {
                $("tbody tr[data-index]", this).each(function () {
                    // if (parseInt($("td:eq(1)", this).text()) == Config.admin.id) {
                    //     $("input[type=checkbox]", this).prop("disabled", true);
                    // }
                });
            });


            var column = [
                // {field: 'merchant_number', title: '商户号'},
                {field: 'id', title: 'id'},
                {
                    field: 'merchant_number', 
                    title: '商户号',
                    formatter: function (value,row){
                        return row.merchant_name+'('+value+')';
                    },
                    searchList: $.getJSON("order/collection/merchantlist")
                },
                {field: 'orderno', title: '系统订单号'},
                {field: 'type', title: '类型',searchList: $.getJSON("record/payment/typeslect")},

                {
                    field: 'status', 
                    title: '状态', 
                    formatter: Table.api.formatter.status,
                    custom: {0: 'error', 1: 'success'},
                    searchList: {0: '失败', 1: '成功'},
                    operate:false
                },
                {field: 'bef_amount', title: '交易前余额',operate:false},
                {field: 'change_amount', title: '交易金额',operate:false},
                {field: 'aft_amount', title: '交易后金额',operate:false},
                // {field: 'aft_amount', title: '备注',operate:false},
                {field: 'create_time', title: '交易时间',operate: 'RANGE', addclass: 'datetimerange', sortable: true},
            ];
            var group_id = Config.admin.group_id;
            if(group_id == 2){
                column = [
                    {field: 'merchant_number', title: '商户号',operate:false},
                    {field: 'merchant_name', title: '商户名称',operate:false},
                    {field: 'orderno', title: '系统订单号',operate:false},
                    {field: 'type', title: '类型',searchList: $.getJSON("record/payment/typeslect")},

                    {
                        field: 'status', 
                        title: '状态', 
                        formatter: Table.api.formatter.status,
                        custom: {0: 'error', 1: 'success'},
                        searchList: {0: '失败', 1: '成功'},
                        operate:false
                    },
                    {field: 'bef_amount', title: '交易前余额',operate:false},
                    {field: 'change_amount', title: '交易金额',operate:false},
                    {field: 'aft_amount', title: '交易后金额',operate:false},
                    {field: 'create_time', title: '交易时间',operate: 'RANGE', addclass: 'datetimerange', sortable: true},
                ]
            }

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                fixedColumns: true,
                fixedNumber: 0,
                fixedRightNumber: 0,
                //禁用默认搜索
                search: false,
                // //启用普通表单搜索
                commonSearch: true,
                // //可以控制是否默认显示搜索单表,false则隐藏,默认为false
                searchFormVisible: true,
                columns: [
                    column
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
    };
    
    return Controller;
});
