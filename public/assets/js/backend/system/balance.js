define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            var _this = this;
            Table.api.init({
                extend: {
                    index_url: 'system/balance/index',
                    add_url: 'system/balance/add',
                    edit_url: 'system/balance/edit',
                    // del_url: 'system/balance/del',
                }
            });

            var table = $("#table");

            let typeslect;

            const typedata = $.getJSON("system/balance/typeslect",function(data){
                typeslect = data;
            });

            //在表格内容渲染完成后回调的事件
            table.on('post-body.bs.table', function (e, json) {
                $("tbody tr[data-index]", this).each(function () {
                    // if (parseInt($("td:eq(1)", this).text()) == Config.admin.id) {
                    //     $("input[type=checkbox]", this).prop("disabled", true);
                    // }
                });
            });


            table.on('load-success.bs.table', function (e, json) {
                // console.log(json)
                let amount = json.extend.value;
                $("#money").text(amount);

                $('.btn-operate').off('click');

                $('.btn-operate').click(function(){
                    layer.open({
                        type: 1,
                        skin: 'layui-layer-demo', //样式类名
                        area: ['400px', '300px'], //宽高
                        closeBtn: 1, //不显示关闭按钮
                        anim: 2,
                        title:'操作',
                        shadeClose: true, //开启遮罩关闭
                        content: _this.innerhtml(amount,typeslect),
                        zIndex:99
                    });
                    _this.checkpost();
                })
                
            });

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                fixedColumns: true,
                fixedNumber: 1,
                fixedRightNumber: 1,
                searchFormVisible: true,
                search: false,
                columns: [
                    [
                        {field: 'id', title: 'id',operate:false},
                        {field: 'merchant_number', title: '商户号'},
                        {field: 'merchant_name', title: '商户名称',operate:false},
                        {field: 'orderno', title: '系统订单号',operate:false},
                        {field: 'type', title: '类型',searchList: typedata},

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
        },
        innerhtml: function(amount,typeslect){
            var content = '<form id="check"><div style="padding: 30px;" class="table-update menulist">\
                    <div class="form-group">\
                        <label><span style="color:red;">*</span>金额: (当前可用收益： '+amount+')</label>\
                        <input type="number" max="'+amount+'" class="form-control " name="amount" autocomplete="off" value="'+amount+'"  placeholder="" id="amount" data-index="7">\
                    </div>\
                    <div class="form-group">\
                        <label>操作类型:</label>\
                        <select id="type" data-rule="required" class="form-control selectpicker" name="type">\
                        <option value="0" >选择</option>';
                            for(var key in typeslect){
                                if(key == 2) continue
                                content += '<option  ';

                                if(key == 1){
                                    content += 'selected';
                                }

                                content +=' value="'+key+'" >'+typeslect[key]+'</option>';
                            }
                            content +=  '</select>\
                    </div>\
                    <button type="submit" class="btn btn-success check_submit" formnovalidate="">提交</button>\
                </div></form>';
            return content;

        },

        checkpost: function(){
            var _this = this;
            $('.check_submit').click(function(){
                var amount = $('#amount').val();

                var type = $('#type').val();

                // if(parseFloat(amount) > parseFloat(merchant_amount)){
                //     Layer.msg('不可超过当前可用余额');
                //     return;
                // }
                $.ajax({
                    type:"POST",
                    url:"system/balance/check",
                    data:{
                        amount:amount,
                        type:type,
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
