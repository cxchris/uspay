define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'system/collection/index',
                    add_url: 'system/collection/add',
                    edit_url: 'system/collection/edit',
                    // del_url: 'system/collection/del',
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
                fixedNumber: 1,
                fixedRightNumber: 1,
                columns: [
                    [
                        // { checkbox: true, },
                        {field: 'id', title: '通道id'},
                        {field: 'channel_name', title: '通道名称'},
                        {field: 'channel_en_name', title: '英文名称'},
                        {field: 'billing_around', title: '结算周期'},
                        {field: 'channel_sign', title: '通道商户号'},
                        {field: 'channel_type', title: '支付渠道'},
                        {field: 'channel_pay_type', title: '支付方式'},
                        {field: 'rate', title: '费率'},
                        // {field: 'channel_key', title: 'key'},
                        {field: 'channel_safe_url', title: '安全域名'},
                        {field: 'low_money', title: '最低金额'},
                        {field: 'high_money', title: '最高金额'},
                        {field: 'day_limit_money', title: '每日限额'},
                        {field: 'status', title: __("Status"), searchList: {"1":__('Normal'),"0":'禁用'}, formatter: Table.api.formatter.status},
                        {field: 'create_time', title: '创建时间', formatter: Table.api.formatter.datetime, operate: 'RANGE', addclass: 'datetimerange', sortable: true},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, 
                            formatter: function (value, row, index) {
                                return Table.api.formatter.operate.call(this, value, row, index);
                            }
                        }
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            Form.api.bindevent($("form[role=form]"));
        },
        edit: function () {
            Form.api.bindevent($("form[role=form]"));
        }
    };
    return Controller;
});
