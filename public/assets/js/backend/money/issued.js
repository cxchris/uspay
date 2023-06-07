define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'money/issued/list',
                }
            });
            var _this = this;

            var table = $("#table");

            //当表格数据加载完成时
            table.on('load-success.bs.table', function (e, json) {
                // console.log(json)
                $("#issusd_total").text(json.extend.issusd_total);
                $("#issusd_money").text(json.extend.issusd_money);
                $("#issusd_success_total").text(json.extend.issusd_success_total);
                $("#issusd_success_money").text(json.extend.issusd_success_money);
                $("#issusd_fail_total").text(json.extend.issusd_fail_total);
                $("#issusd_fail_money").text(json.extend.issusd_fail_money);
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
                        {field: 'merchant_name', title: '商户名',operate:false},
                        {field: 'bef_money', title: '下发前余额',operate:false},
                        {field: 'money', title: '下发金额',operate:false},
                        {field: 'aft_money', title: '下发后余额',operate:false},
                        {field: 'status', title: '状态',operate:false},
                        {field: 'create_time', title: '创建时间',  operate: 'RANGE', addclass: 'datetimerange', sortable: true},
                        {field: 'remark', title: '备注',operate:false},
                        {field: 'update_time', title: '修改时间',  operate: 'RANGE', addclass: 'datetimerange', sortable: true,operate:false},
                        // {field: 'bank_id', title: '银行信息',operate:false},
                        {
                            field: 'operate',title: '银行信息',table: table,
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
                        }
                        
                    ]
                ],
            });

            $("input[type='text']").each(function (index) {
                this.autocomplete = "off";
            })

            //结算
            $('.btn-issued').click(function(){
                $.ajax({
                    type:"POST",
                    url:"money/issued/banklist",
                    data:{
                        
                    },
                    dataType:"json",
                    success:function (data) {
                        layer.open({
                            type: 1,
                            skin: 'layui-layer-demo', //样式类名
                            area: ['400px', '500px'], //宽高
                            closeBtn: 1, //不显示关闭按钮
                            anim: 2,
                            title:'申请下发',
                            shadeClose: true, //开启遮罩关闭
                            content: _this.innerhtml(data),
                            zIndex:99
                        });

                        Form.api.bindevent($("#issued"));
                        _this.checkpost();

                    }
                })
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
        innerhtml: function(data){
            var merchant_amount = Config.merchant_amount;
            var content = '<form id="check"><div style="padding: 30px;" class="table-update menulist">\
                     <div class="form-group">\
                        <label><span style="color:red;">*</span>金额: (当前可用金额：₹ '+merchant_amount+')</label>\
                        <input type="number" class="form-control " name="amount" autocomplete="off" value=""  placeholder="" id="amount" data-index="7">\
                    </div>\
                    <div class="form-group">\
                        <label><span style="color:red;">*</span>银行账户:</label>\
                        <select id="bank_id" data-rule="required" class="form-control selectpicker" name="bank_id">\
                        <option value="0" >选择</option>';
                for (var i=0; i < data.length; i++){
                    content += '<option ';

                    if(data[i].is_default == 1){
                        content += 'selected';
                    }

                    content += ' value="'+data[i].id+'" >'+data[i].account+'（'+data[i].banknumber+'）</option>';
                }
                content +=  '</select>\
                    </div>\
                    <div class="form-group">\
                        <label>Google双层验证设定:</label>\
                        <input type="text" class="form-control " name="checknum" autocomplete="off" value=""  placeholder="选填" id="checknum" data-index="7">\
                    </div>\
                    <div class="form-group">\
                        <label>备注:</label>\
                        <input type="text" class="form-control " name="remark" autocomplete="off" value=""  placeholder="选填" id="remark" data-index="7">\
                    </div>\
                    <button type="submit" class="btn btn-success check_submit" formnovalidate="">申请下发</button>\
                </div></form>';
            return content;

        },
        //提交
        checkpost: function(){
            var merchant_amount = Config.merchant_amount;
            var _this = this;
            $('.check_submit').click(function(){
                var amount = $('#amount').val();
                var bank_id = $('#bank_id').val();
                var checknum = $('#checknum').val();
                var remark = $('#remark').val();

                if(parseFloat(amount) > parseFloat(merchant_amount)){
                    Layer.msg('不可超过当前可用余额');
                    return;
                }
                $.ajax({
                    type:"POST",
                    url:"money/issued/apply",
                    data:{
                        amount:amount,
                        bank_id:bank_id,
                        checknum:checknum,
                        remark:remark,
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
