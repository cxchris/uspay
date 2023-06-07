define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'auth/admin/index',
                    add_url: 'auth/admin/add',
                    edit_url: 'auth/admin/edit',
                    del_url: 'auth/admin/del',
                    multi_url: 'auth/admin/multi',
                }
            });

            var table = $("#table");

            //在表格内容渲染完成后回调的事件
            table.on('post-body.bs.table', function (e, json) {
                $("tbody tr[data-index]", this).each(function () {
                    if (parseInt($("td:eq(1)", this).text()) == Config.admin.id) {
                        $("input[type=checkbox]", this).prop("disabled", true);
                    }
                });
            });

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                columns: [
                    [
                        {field: 'state', checkbox: true, },
                        {field: 'id', title: 'ID'},
                        {field: 'username', title: __('Username')},
                        {field: 'nickname', title: __('Nickname')},
                        {field: 'checkSum', title: 'Google key'},
                        {field: 'groups_text', title: __('Group'), operate:false, formatter: Table.api.formatter.label},
                        // {field: 'email', title: __('Email')},
                        {field: 'status', title: __("Status"), searchList: {"normal":__('Normal'),"hidden":__('Hidden')}, formatter: Table.api.formatter.status},
                        {field: 'logintime', title: __('Login time'), formatter: Table.api.formatter.datetime, operate: 'RANGE', addclass: 'datetimerange', sortable: true},

                        {
                            field: 'operate',title: '操作',table: table,
                            events: Controller.api.events.operate, 
                            formatter: Controller.api.formatter.operate,
                            buttons: [
                                {
                                    name: 'reset',
                                    text: '重置密钥',
                                    icon: 'fa fa-primary',
                                    classname: 'btn btn-info btn-xs btn-detail btn-googleReset',
                                },
                                {
                                    name: 'google',
                                    text: '谷歌身份验证器',
                                    icon: 'fa fa-primary',
                                    classname: 'btn btn-info btn-xs btn-detail btn-googleVaild',
                                    
                                },
                            ],
                        }

                        // {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: function (value, row, index) {
                        //         if(row.id == Config.admin.id){
                        //             return '';
                        //         }
                        //         return Table.api.formatter.operate.call(this, value, row, index);
                        //     }}
                    ]
                ]
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
                    'click .btn-googleVaild': function (e, value,row,index) {
                        var that = this;
                        var table = $(that).closest('table'); 
                        var options = table.bootstrapTable('getOptions');
                        var load = Layer.prompt({title: 'Google Checksum', shadeClose: true}, function (text, index) {
                            if($.trim(text)==''){
                                Layer.msg('不能为空');
                                return false
                            }

                            var ids = row['id'];
                            var Checksum = $.trim(text);

                            $.ajax({
                                type:"POST",
                                url:"google/get",
                                data:{
                                    id:ids,
                                    Checksum:Checksum
                                },
                                dataType:"json",
                                success:function (data) {
                                    // console.log(data);
                                    layer.close(load)
                                    if(data.code == 1){
                                        Layer.alert(data.data.Checksum)
                                        // Layer.closeAll()
                                        // $('.btn-refresh').trigger('click')
                                    }else{
                                        Layer.alert(data.msg)
                                    }

                                }
                            })
                        })
                    },
                    'click .btn-googleReset': function (e, value,row,index) {
                        var that = this;
                        var table = $(that).closest('table'); 
                        var options = table.bootstrapTable('getOptions');
                        var load = Layer.prompt({title: 'Google Checksum', shadeClose: true}, function (text, index) {
                            if($.trim(text)==''){
                                Layer.msg('不能为空');
                                return false
                            }

                            var ids = row['id'];
                            var Checksum = $.trim(text);

                            $.ajax({
                                type:"POST",
                                url:"google/reset",
                                data:{
                                    id:ids,
                                    Checksum:Checksum
                                },
                                dataType:"json",
                                success:function (data) {
                                    // console.log(data);
                                    layer.close(load)
                                    if(data.code == 1){
                                        Layer.alert(data.data.Checksum)
                                        // Layer.closeAll()
                                        // $('.btn-refresh').trigger('click')
                                    }else{
                                        Layer.alert(data.msg)
                                    }

                                }
                            })
                        })
                    },
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

                    
                    if (options.extend.edit_url !== '' && names.indexOf('edit') === -1) {
                        buttons.push({
                            name: 'edit',
                            icon: 'fa fa-pencil',
                            title: __('Edit'),
                            extend: 'data-toggle="tooltip" ',
                            classname: 'btn btn-xs btn-success btn-dialog ',
                            url: options.extend.edit_url
                        });
                    }

                    // if (options.extend.del_url !== '' && names.indexOf('del') === -1) {
                    //     buttons.push({
                    //         name: 'del',
                    //         icon: 'fa fa-pencil',
                    //         title: __('Del'),
                    //         extend: 'data-toggle="tooltip"',
                    //         classname: 'btn btn-xs btn-danger btn-delone',
                    //         // url: options.extend.del_url
                    //     });
                    // }

                    return Table.api.buttonlink(this, buttons, value, row, index, 'operate');
                }
            }
        },
        add: function () {
            Controller.api.bindevent($("form[role=form]"));
        },
        edit: function () {
            Controller.api.bindevent($("form[role=form]"));
        }
    };
    return Controller;
});
