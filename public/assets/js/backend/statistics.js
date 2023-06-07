define(['jquery', 'bootstrap', 'backend', 'addtabs', 'table', 'echarts', 'echarts-theme', 'template', 'layui'], function ($, undefined, Backend, Datatable, Table, Echarts, undefined, Template, layui) {

    var Controller = {
        index: function () {
            // 基于准备好的dom，初始化echarts实例
            var myChart = Echarts.init(document.getElementById('money'), 'walden');

            // 指定图表的配置项和数据
            var option = {
                title: {
                    text: '近15天成功金额',
                    subtext: ''
                },
                color: [
                    "#18d1b1",
                    "#3fb1e3",
                    "#626c91",
                    "#a0a7e6",
                    "#c4ebad",
                    "#96dee8"
                ],
                tooltip: {
                    trigger: 'axis'
                },
                legend: {
                    data: [''],
                    icon: 'circle',
                    top:0,
                    right:0,
                },
                toolbox: {
                    show: false,
                    feature: {
                        magicType: {show: true, type: ['stack', 'tiled']},
                        saveAsImage: {show: true}
                    }
                },
                xAxis: {
                    type: 'category',
                    // boundaryGap: false,
                    boundaryGap : true,
                    xAxisTicks  : {
                        alignWithLabel  : true
                    },
                    data: Config.money_column, //Config.column
                },
                yAxis: {
                    type: 'value',
                    axisLine: {
                        onZero: false // y轴是否在x轴0刻度上
                    }
                },
                grid: [{
                    left: 'left',
                    top: 'top',
                    right: '10',
                    bottom: 30
                }],
                series: [{
                    name: '代收成功金额',
                    type: 'line',
                    smooth: true,
                    areaStyle: {
                        normal: {}
                    },
                    lineStyle: {
                        normal: {
                            width: 1.5
                        }
                    },
                    data: Config.money_data, //Config.userdata
                }]
            };

            // 使用刚指定的配置项和数据显示图表。
            myChart.setOption(option);

            

            // $(window).resize(function () {
            //     myChart.resize();
            // });

            // $(document).on("click", ".btn-refresh", function () {
            //     setTimeout(function () {
            //         myChart.resize();
            //     }, 0);
            // });



                
        }
    };

    layui.use('carousel', function(){
        var carousel = layui.carousel;
        //建造实例
        carousel.render({
            elem: '#test1',
            width: '100%', //设置容器宽度
            height: '300px',
            autoplay: false,
            indicator: 'outside',
            arrow: 'none', //始终显示箭头
            //,anim: 'updown' //切换动画方式
        });

        carousel.on('change(test1)', function(obj){ //test1来源于对应HTML容器的 lay-filter="test1" 属性值
            if(obj.index == 1){
                var myChart2 = Echarts.init(document.getElementById('total'), 'walden');

                // 指定图表的配置项和数据
                var option2 = {
                    title: {
                        text: '近15天成功订单数',
                        subtext: ''
                    },
                    color: [
                        "#3fb1e3",
                        "#18d1b1",
                        "#626c91",
                        "#a0a7e6",
                        "#c4ebad",
                        "#96dee8"
                    ],
                    tooltip: {
                        trigger: 'axis'
                    },
                    legend: {
                        data: [''],
                        icon: 'circle',
                        top:0,
                        right:0,
                    },
                    toolbox: {
                        show: false,
                        feature: {
                            magicType: {show: true, type: ['stack', 'tiled']},
                            saveAsImage: {show: true}
                        }
                    },
                    xAxis: {
                        type: 'category',
                        // boundaryGap: false,
                        boundaryGap : true,
                        xAxisTicks  : {
                            alignWithLabel  : true
                        },
                        data: Config.total_column, //Config.column
                    },
                    yAxis: {
                        type: 'value',
                        axisLine: {
                            onZero: false // y轴是否在x轴0刻度上
                        }
                    },
                    grid: [{
                        left: 'left',
                        top: 'top',
                        right: '10',
                        bottom: 30
                    }],
                    series: [{
                        name: '订单数',
                        type: 'line',
                        smooth: true,
                        areaStyle: {
                            normal: {}
                        },
                        lineStyle: {
                            normal: {
                                width: 1.5
                            }
                        },
                        data: Config.total_data, //Config.money_data
                    }]
                };

                // 使用刚指定的配置项和数据显示图表。
                myChart2.setOption(option2);
            }
        });
    });


    return Controller;
});

