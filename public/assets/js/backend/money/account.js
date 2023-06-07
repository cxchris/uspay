define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var group_id = Config.admin.group_id;
    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'money/account/index',
                    add_url: 'money/account/add',
                    edit_url: 'money/account/edit',
                    del_url: 'money/account/del',
                }
            });
            var _this = this;

            var table = $("#table");

            //当表格数据加载完成时
            table.on('load-success.bs.table', function (e, json) {
                // console.log(json)
            });

            var columns,commonSearchstatus;
            if(group_id == 1){
                commonSearchstatus = true;
                columns = [
                    [
                        {
                            field: 'merchant_number', 
                            title: '商户',
                            formatter: function (value,row) 
                            {
                                return row.merchant_name+'('+value+')';
                            },
                            searchList: $.getJSON("order/collection/merchantlist")
                        },
                        {field: 'account', title: '账户名',operate:false},
                        {field: 'bankname', title: '银行名称',operate:false},
                        {field: 'banknumber', title: '银行账户',operate:false},
                        {field: 'ifsccode', title: 'IFSC Code',operate:false},
                        // {field: 'create_time', title: '创建时间',  operate: 'RANGE', addclass: 'datetimerange', sortable: true,operate:false},
                        // {field: 'remark', title: '备注',operate:false},
                        {
                            field: 'operate', 
                            title: __('Operate'), 
                            table: table, 
                            events: Table.api.events.operate, 
                            formatter: function (value, row, index) {
                                return Table.api.formatter.operate.call(this, value, row, index);
                            }
                        }
                        
                    ]
                ];
            }else{
                commonSearchstatus = false;
                columns = [
                    [
                        {field: 'merchant_name', title: '商户',operate:false},
                        {field: 'account', title: '账户名',operate:false},
                        {field: 'bankname', title: '银行名称',operate:false},
                        {field: 'banknumber', title: '银行账户',operate:false},
                        {field: 'ifsccode', title: 'IFSC Code',operate:false},
                        // {field: 'create_time', title: '创建时间',  operate: 'RANGE', addclass: 'datetimerange', sortable: true,operate:false},
                        // {field: 'remark', title: '备注',operate:false},
                        {
                            field: 'operate', 
                            title: __('Operate'), 
                            table: table, 
                            events: Table.api.events.operate, 
                            formatter: function (value, row, index) {
                                return Table.api.formatter.operate.call(this, value, row, index);
                            }
                        }
                        
                    ]
                ];
            }

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                fixedColumns: true,
                fixedNumber: 0,
                fixedRightNumber: 0,
                //禁用默认搜索
                search: false,
                //启用普通表单搜索
                commonSearch: commonSearchstatus,
                //可以控制是否默认显示搜索单表,false则隐藏,默认为false
                searchFormVisible: true,
                // queryParams : function (params) {
                //     params.type = 1;
                //     return params;
                // },
                columns: columns
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
