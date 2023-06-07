define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'money/transfer/list',
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
                // queryParams : function (params) {
                //     params.type = 1;
                //     return params;
                // },
                columns: [
                    [
                        {field: 'merchant_number', title: '商户号',operate:false},
                        {field: 'bef_amount', title: '转移前代付金额',operate:false},
                        {field: 'change_amount', title: '转移金额',operate:false},
                        {field: 'aft_amount', title: '转移后代付金额',operate:false},
                        {field: 'create_time', title: '创建时间',  operate: 'RANGE', addclass: 'datetimerange', sortable: true},
                        // {field: 'note', title: '说明',operate:false},
                        
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
        innerhtml: function(){
            var merchant_amount = Config.merchant_amount;
            var merchant_payment_amount = Config.merchant_payment_amount;
            var content = '<form id="check"><div style="padding: 30px;" class="table-update menulist">\
                     <div class="form-group">\
                        <label>代收余额: <span style="color:red;">(当前可用金额：₹ '+merchant_amount+')</span></label>\
                        <input type="number" class="form-control " readonly  autocomplete="off" value="'+merchant_amount+'"  placeholder=""  data-index="7">\
                    </div>\
                    <div class="form-group">\
                        <label>金额: </label>\
                        <input type="number" class="form-control " name="amount" autocomplete="off" value=""  placeholder="" id="amount" data-index="7">\
                    </div>\
                    <div class="form-group">\
                        <label>代付余额: (当前：₹ '+merchant_payment_amount+')</label>\
                        <input type="number" class="form-control " readonly  autocomplete="off" value="'+merchant_payment_amount+'"  placeholder=""  data-index="7">\
                    </div>\
                    <button type="submit" class="btn btn-success check_submit" formnovalidate="">将金额转移至代付</button>\
                </div></form>';
            return content;

        },
        //提交
        transferpost: function(){
            var merchant_amount = Config.merchant_amount;
            var _this = this;
            $('.check_submit').click(function(){
                var amount = $('#amount').val();

                if(parseFloat(amount) > parseFloat(merchant_amount)){
                    Layer.msg('不可超过当前可用余额');
                    return;
                }
                $.ajax({
                    type:"POST",
                    url:"money/transfer/transfer",
                    data:{
                        amount:amount,
                    },
                    dataType:"json",
                    success:function (data) {
                        console.log(data);
                        // layer.close(load)
                        if(data.code == 1){
                            Layer.msg(data.msg)
                            window.parent.location.reload();
                            // Layer.closeAll()
                            // $('.btn-refresh').trigger('click')
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
    
});
