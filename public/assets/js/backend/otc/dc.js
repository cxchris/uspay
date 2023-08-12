define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init();
            this.table.first();
            this.table.second();
        },
        table:{
            first: function(){
                var table = $("#table");
                var extend = {
                    index_url: 'otc/dc/index',
                    add_url: 'otc/dc/add',
                    edit_url: 'otc/dc/edit',
                    del_url: 'otc/dc/del',

                }

                //当表格数据加载完成时
                table.on('load-success.bs.table', function (e, json) {
                    // console.log(json)
                    // $("#money").text(json.extend.money);
                    // $("#total").text(json.extend.total);
                    // $("#price").text(json.extend.price);
                    // $("#tax").text(json.extend.tax);
                    // $("#rate").text(json.extend.rate);
                });

                table.on('post-body.bs.table', function (e, settings, json, xhr) {
                    $(".btn-amount").data("end", function(){
                        //关闭后的事件
                        $('.btn-refresh').trigger('click')
                    });
                });

                // 初始化表格
                table.bootstrapTable({
                    toolbar: "#toolbar",
                    extend: extend,
                    url: extend.index_url,
                    fixedColumns: true,
                    fixedNumber: 0,
                    fixedRightNumber: 1,
                    //禁用默认搜索
                    search: false,
                    //启用普通表单搜索
                    commonSearch: true,
                    //可以控制是否默认显示搜索单表,false则隐藏,默认为false
                    searchFormVisible: true,
                    columns: [
                        [
                            {field: 'id', title: 'id'},
                            {
                                field: 'address', 
                                title: '地址',
                                formatter: function (value, row, index) {
                                    // 在地址列旁边添加复制按钮
                                    return value + ' <a href="javascript:;" class="btn btn-xs btn-success btn-copy btn-copy-'+row.id+'" data-clipboard-text="' + value + '" data-address="' + row.id + '"  title="复制">' +
                                       '<i class="fa fa-copy"></i>' +
                                       '</a>';
                                }
                            },
                            // {field: 'privateKey', title: '密钥', operate:false,
                            //     formatter: function (value, row, index) {
                            //         // 在地址列旁边添加复制按钮
                            //         return value + ' <a href="javascript:;" class="btn btn-xs btn-success btn-search" onclick="searchKey(\'' + row.id + '\')" title="查看">' +
                            //            '<i class="fa fa-search"></i>' +
                            //            '</a>';
                            //     }
                            // },
                            {field: 'amount', title: '余额', operate:false, 
                                formatter: function (value, row, index) {
                                    // 在地址列旁边添加复制按钮
                                    return value + ' <a href="javascript:;" class="btn btn-xs btn-success btn-refresh-amount" data-detail="' + row.id + '" title="查看">' +
                                       '<i class="fa fa-refresh"></i>' +
                                       '</a>';
                                }
                            },
                            {field: 'type', title: '类型',searchList: $.getJSON("otc/dc/typeslect")},

                            {
                                field: 'status', 
                                title: '状态', 
                                formatter: Table.api.formatter.status,
                                custom: {0: 'error', 1: 'success'},
                                searchList: {0: '关闭',1: '启用'}
                            },
                            {field: 'create_time', title: '创建时间',  operate: 'RANGE', addclass: 'datetimerange', sortable: true,operate:false},

                            {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, 
                                formatter: function (value, row, index) {
                                    return Table.api.formatter.operate.call(this, value, row, index);
                                }
                            }
                            
                        ]
                    ],
                });



                table.parent().on('click', '.btn-copy', function() {
                    // 获取地址并调用 copyAddress 函数
                    var address = $(this).data('address');
                    copyAddress(address);
                });

                $(document).on('click', '.btn-refresh-amount', function() {
                    var $btn = $(this);
                    var id = $btn.data('detail');
                    var rowIndex = $btn.closest('tr').data('index');

                    // 在此处发起刷新操作，获取最新的余额值，假设获取到新的余额值为 newAmount
                    showDetail(id,function(amount){
                        table.bootstrapTable('updateRow', {index: rowIndex, row: {amount: amount}});
                    });
                    
                });

                // table.parent().on('click', '.btn-refresh', function() {
                //     var $btn = $(this);
                //     var id = $btn.data('detail');

                //     // showDetail(id);
                // });

                $("input[type='text']").each(function (index) {
                    this.autocomplete = "off";
                })

                Form.api.bindevent("form[role=form]");

                $(document).on("change", "#c-express", function(){
                    //开关切换后的回调事件
                    const switchElement = $(this);
                    const val = $('#c-express').val();
                    const inputId = switchElement.data("input-id");
                    const currentValue = $("#" + inputId).val();
                    const newValue = currentValue === "1" ? "0" : "1";
                    console.log(newValue)

                    $.ajax({
                        type:"POST",
                        url:"otc/dc/express",
                        data:{
                            val:val,
                        },
                        dataType:"json",
                        success:function (data) {
                            console.log(data);
                            // layer.close(load)
                            if(data.code == 1){
                                // $("#" + inputId).val(newValue);
                                Layer.msg(data.msg)
                                // Layer.closeAll()
                            }else{
                                Layer.alert(data.msg, {icon: 5})
                            }

                        }
                    })

                });

                // 为表格绑定事件
                Table.api.bindevent(table);

            },
            second: function(){
                var _this = this;
                var extend = {
                    index_url: 'otc/dclist/index',
                    add_url: 'otc/dclist/add',
                    edit_url: 'otc/dclist/edit',
                    del_url: 'otc/dclist/del',

                }
                var table2 = $("#table2");
                table2.on('load-success.bs.table', function (e, json) {
                });
                table2.on('post-body.bs.table', function (e, settings, json, xhr) {
                });
                table2.bootstrapTable({
                    toolbar: "#toolbar1",
                    url: extend.index_url,
                    extend: extend,
                    fixedColumns: true,
                    fixedNumber: 0,
                    fixedRightNumber: 1,
                    //禁用默认搜索
                    search: false,
                    //启用普通表单搜索
                    commonSearch: true,
                    //可以控制是否默认显示搜索单表,false则隐藏,默认为false
                    searchFormVisible: true,
                    columns: [
                        [
                            {field: 'id', title: 'id'},
                            {field: 'name', title: '名字'},
                            {field: 'otcid', title: 'otcid'},

                            {
                                field: 'status', 
                                title: '状态', 
                                formatter: Table.api.formatter.status,
                                custom: {0: 'error', 1: 'success'},
                                searchList: {0: '关闭',1: '启用'}
                            },

                            {field: 'operate', title: __('Operate'), table: table2, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                            
                        ]
                    ],
                });
                Table.api.bindevent(table2);
            },
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
            Form.api.bindevent($("form[role=form1]"));
        },
        edit: function () {
            Form.api.bindevent($("form[role=form]"));
            Form.api.bindevent($("form[role=form1]"));
        },
    };

    //
    function copyAddress(address){
        // 初始化 Clipboard.js
        var clipboard = new Clipboard('.btn-copy-'+address);

        // 复制成功的回调函数
        clipboard.on('success', function (e) {
            console.log('复制成功：', e.text);
            layer.closeAll()
            layer.msg('复制成功');
        });
        
    }

    //展示
    function showDetail(id,func){
        var loadIndex = layer.load(2, {time: 0, shade: [0.3, '#000']}); // 0表示加载图标，不限定时间
        $.ajax({
            type:"POST",
            url:"otc/dc/amount",
            data:{
                id:id,
            },
            dataType:"json",
            success:function (data) {
                layer.close(loadIndex);
                console.log(data);
                // layer.close(load)
                if(data.code == 200){
                    Layer.msg(data.msg)
                    let amount = data.data.balanceDecimal;
                    func(amount)
                    // Layer.closeAll()
                    // $('.btn-refresh').trigger('click')
                }else{
                    Layer.alert(data.msg, {icon: 5})
                    // $('.btn-refresh').trigger('click')
                }

            },
            error: function (error) {
                // 在请求失败后关闭 Loading
                layer.close(loadIndex);

                // 处理错误情况
                console.error(error);
            }
        })
    }
    return Controller;
    
});
