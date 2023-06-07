define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'manage/daily/index',
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
                            field: 'starttime',
                            title: '开始日期',
                            operate: 'RANGE',
                            addclass: 'datetimepicker',
                            visible:false,
                            // data: 'data-date-format="YYYY-MM-DD"',
                            formatter: Table.api.formatter.date,
                            data:'autocomplete="off" data-date-format="YYYY-MM-DD" '
                        },
                        {
                            field: 'endtime',
                            title: '结束日期',
                            operate: 'RANGE',
                            addclass: 'datetimepicker',
                            visible:false,
                            data: 'autocomplete="off" data-date-format="YYYY-MM-DD"',
                            formatter: Table.api.formatter.date
                        },
                        {field: 'datetime', title: '日期',operate:false},
                        {field: 'amount', title: '代收总金额',operate:false},
                        // {field: 'amount_settlement', title: '代收未结算',operate:false},
                        {field: 'amount_tax', title: '代收手续费',operate:false},
                        {field: 'amount_check', title: '代收结算金额',operate:false},

                        {field: 'payment', title: '代付金额',operate:false},
                        // {field: 'aft_amount', title: '代付订单',operate:false},
                        {field: 'payment_tax', title: '代付手续费',operate:false},
                        
                    ]
                ],
            });

            $("input[type='text']").each(function (index) {
                this.autocomplete = "off";
            })

            //将金额转移至代付
            $('.btn-transfer').click(function(){
                layer.open({
                    type: 1,
                    skin: 'layui-layer-demo', //样式类名
                    area: ['400px', '500px'], //宽高
                    closeBtn: 1, //不显示关闭按钮
                    anim: 2,
                    title:'将金额转移至代付',
                    shadeClose: true, //开启遮罩关闭
                    content: _this.innerhtml(),
                    zIndex:99
                });

                Form.api.bindevent($("#transfer"));
                _this.transferpost();
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
    };
    return Controller;
    
});
