define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'order/issued/list',
                }
            });
            var _this = this;

            var table = $("#table");

            //当表格数据加载完成时
            table.on('load-success.bs.table', function (e, json) {
                // console.log(json)
                
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
                // queryParams : function (params) {
                //     params.type = 1;
                //     return params;
                // },
                columns: [
                    [
                        // {field: 'merchant_name', title: '商户名',operate:false},
                        // {field: 'merchant_number',  title: '商户号'},
                        {
                            field: 'merchant_number', 
                            title: '商户号',
                            formatter: function (value,row){
                                return row.merchant_name+'('+value+')';
                            },
                            searchList: $.getJSON("order/collection/merchantlist")
                        },
                        {field: 'bef_money', title: '下发前余额',operate:false},
                        {field: 'money', title: '下发金额',operate:false},
                        {field: 'aft_money', title: '下发后余额',operate:false},
                        {field: 'status_name', title: '状态',operate:false},
                        {field: 'create_time', title: '创建时间',  operate: 'RANGE', addclass: 'datetimerange', sortable: true},
                        {field: 'remark', title: '备注',operate:false},
                        {field: 'update_time', title: '修改时间',  operate: 'RANGE', addclass: 'datetimerange', sortable: true,operate:false},
                        // {field: 'bank_id', title: '银行信息',operate:false},
                        {
                            field: 'operate',title: '操作',table: table,
                            events: Controller.api.events.operate, 
                            formatter: Controller.api.formatter.operate,
                            buttons: [
                                {
                                    name: 'amount',
                                    text: '查看银行信息',
                                    icon: 'fa fa-primary',
                                    classname: 'btn btn-xs btn-info btn-amount btn-dialog',
                                    extend: 'data-toggle="tooltip" ',
                                    url: 'money/issued/detail',
                                },
                            ],
                        },
                        
                    ]
                ],
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
                operate:{
                    'click .btn-pass': function (e, value,row,index) {
                        var that = this;
                        var table = $(that).closest('table'); 
                        var options = table.bootstrapTable('getOptions');
                        var load = Layer.confirm('是否通过商户的下发申请',{icon: 1}, function (text, index) {
                            var ids = row['id'];

                            $.ajax({
                                type:"POST",
                                url:"order/issued/operate",
                                data:{
                                    id:ids,
                                    type: 1
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
                    'click .btn-refuse': function (e, value,row,index) {
                        var that = this;
                        var table = $(that).closest('table'); 
                        var options = table.bootstrapTable('getOptions');
                        var load = Layer.confirm('是否拒绝商户的下发申请（拒绝后金额回滚至商户余额）', {icon: 2},function (text, index) {
                            var ids = row['id'];

                            $.ajax({
                                type:"POST",
                                url:"order/issued/operate",
                                data:{
                                    id:ids,
                                    type: 2
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
                    
                },
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

                    if(row.status == 0){
                        buttons.push({
                            name: 'notice',
                            text: '通过',
                            icon: 'fa fa-info',
                            classname: 'btn btn-primary btn-xs btn-pass',
                        });

                        buttons.push({
                            name: 'refuse',
                            text: '拒绝',
                            icon: 'fa fa-warning',
                            classname: 'btn btn-warning btn-xs btn-refuse',
                        });
                    }else{
                        var classname = 'btn-info';
                        if(row.status == 2){
                            classname = 'btn-danger';
                        }
                        buttons.push({
                            name: 'notice',
                            text: '已'+row.status_name,
                            icon: 'fa fa-primary',
                            classname: 'btn '+classname+' btn-xs ',
                        });
                    }

                    return Table.api.buttonlink(this, buttons, value, row, index, 'operate');
                },

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
