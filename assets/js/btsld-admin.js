/**
 * Bot Traffic Shield - Admin Dashboard & Chart Engine
 * Version: 1.0.5
 */
(function($) {
    'use strict';

    let trafficChartInstance = null;
    let donutChartInstance = null;
    let currentRange = '7';

    $(document).ready(function() {
        initTabs();
        initCharts();
        initTimeframeSelector();
        initBulkAiActions();
        initClearLogs();
    });

    /**
     * Tab Navigation Controller
     */
    function initTabs() {
        const $tabs = $('.btsld-tabs-nav .nav-tab');
        const $contents = $('.btsld-tab-content');

        // Handle URL hash on initial page load (with fallback for old hash anchors)
        let hash = window.location.hash;
        if (hash === '#settings') hash = '#tab-settings';
        if (hash === '#log') hash = '#tab-log';
        if (hash === '#blocked-bots') hash = '#tab-ai-crawlers';

        if (hash && $(hash).length) {
            $tabs.removeClass('nav-tab-active');
            $tabs.filter('[href="' + hash + '"]').addClass('nav-tab-active');
            $contents.removeClass('btsld-tab-active').hide();
            $(hash).addClass('btsld-tab-active').fadeIn(150);
        }

        // Tab click event
        $tabs.on('click', function(e) {
            e.preventDefault();
            const targetId = $(this).attr('href');

            if (!$(targetId).length) return;

            $tabs.removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');

            $contents.removeClass('btsld-tab-active').hide();
            $(targetId).addClass('btsld-tab-active').fadeIn(150, function() {
                // Trigger chart redraw when switching back to dashboard
                if (targetId === '#tab-dashboard' && trafficChartInstance) {
                    trafficChartInstance.resize();
                    if (donutChartInstance) donutChartInstance.resize();
                }
            });

            if (history.pushState) {
                history.pushState(null, null, targetId);
            } else {
                window.location.hash = targetId;
            }
        });
    }

    /**
     * Chart.js Analytics Engine
     */
    function initCharts() {
        if (typeof Chart === 'undefined' || !btsld_admin.chartData) {
            return;
        }

        const dataSet = btsld_admin.chartData[currentRange] || btsld_admin.chartData['7'];

        // 1. Line Chart: Blocked Traffic Trend
        const ctxTraffic = document.getElementById('btsldTrafficChart');
        if (ctxTraffic) {
            const ctx = ctxTraffic.getContext('2d');
            const gradient = ctx.createLinearGradient(0, 0, 0, 260);
            gradient.addColorStop(0, 'rgba(59, 130, 246, 0.35)');
            gradient.addColorStop(1, 'rgba(59, 130, 246, 0.00)');

            trafficChartInstance = new Chart(ctxTraffic, {
                type: 'line',
                data: {
                    labels: dataSet.labels,
                    datasets: [{
                        label: 'Blocked Requests',
                        data: dataSet.series,
                        borderColor: '#3b82f6',
                        backgroundColor: gradient,
                        borderWidth: 2.5,
                        fill: true,
                        tension: 0.35,
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: '#2563eb',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#0f172a',
                            titleFont: { size: 12, weight: '600' },
                            bodyFont: { size: 12 },
                            padding: 10,
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    return context.parsed.y + ' bots blocked';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0,
                                font: { size: 11 },
                                color: '#64748b'
                            },
                            grid: {
                                color: '#f1f5f9'
                            }
                        },
                        x: {
                            ticks: {
                                font: { size: 11 },
                                color: '#64748b'
                            },
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }

        // 2. Donut Chart: Bot Breakdown
        const ctxDonut = document.getElementById('btsldDonutChart');
        if (ctxDonut) {
            const botKeys = Object.keys(dataSet.bot_totals || {});
            const botCounts = Object.values(dataSet.bot_totals || {});

            const hasData = botCounts.length > 0 && botCounts.reduce((a, b) => a + b, 0) > 0;

            const chartLabels = hasData ? botKeys : ['No Bot Hits Yet'];
            const chartDataValues = hasData ? botCounts : [1];
            const chartColors = hasData
                ? ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4', '#64748b', '#e2e8f0']
                : ['#e2e8f0'];

            donutChartInstance = new Chart(ctxDonut, {
                type: 'doughnut',
                data: {
                    labels: chartLabels,
                    datasets: [{
                        data: chartDataValues,
                        backgroundColor: chartColors,
                        borderWidth: 2,
                        borderColor: '#ffffff',
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                padding: 10,
                                font: { size: 11 },
                                color: '#475569'
                            }
                        },
                        tooltip: {
                            enabled: hasData,
                            backgroundColor: '#0f172a',
                            padding: 10,
                            callbacks: {
                                label: function(context) {
                                    return ' ' + context.label + ': ' + context.parsed + ' hits';
                                }
                            }
                        }
                    }
                }
            });
        }
    }

    /**
     * Timeframe Selector (7 / 14 / 30 Days)
     */
    function initTimeframeSelector() {
        $('.btsld-tf-btn').on('click', function(e) {
            e.preventDefault();

            $('.btsld-tf-btn').removeClass('active');
            $(this).addClass('active');

            const range = $(this).data('range');
            currentRange = String(range);

            if (!btsld_admin.chartData || !btsld_admin.chartData[currentRange]) {
                return;
            }

            const dataSet = btsld_admin.chartData[currentRange];

            // Update Line Chart
            if (trafficChartInstance) {
                trafficChartInstance.data.labels = dataSet.labels;
                trafficChartInstance.data.datasets[0].data = dataSet.series;
                trafficChartInstance.update();
            }

            // Update Donut Chart
            if (donutChartInstance) {
                const botKeys = Object.keys(dataSet.bot_totals || {});
                const botCounts = Object.values(dataSet.bot_totals || {});
                const hasData = botCounts.length > 0 && botCounts.reduce((a, b) => a + b, 0) > 0;

                if (hasData) {
                    donutChartInstance.data.labels = botKeys;
                    donutChartInstance.data.datasets[0].data = botCounts;
                    donutChartInstance.data.datasets[0].backgroundColor = [
                        '#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4', '#64748b', '#e2e8f0'
                    ];
                    donutChartInstance.options.plugins.tooltip.enabled = true;
                } else {
                    donutChartInstance.data.labels = ['No Bot Hits Yet'];
                    donutChartInstance.data.datasets[0].data = [1];
                    donutChartInstance.data.datasets[0].backgroundColor = ['#e2e8f0'];
                    donutChartInstance.options.plugins.tooltip.enabled = false;
                }
                donutChartInstance.update();
            }
        });
    }

    /**
     * AI Crawler Bulk Actions (Select All / Deselect All)
     */
    function initBulkAiActions() {
        $('#btsld-select-all-ai').on('click', function(e) {
            e.preventDefault();
            $('.btsld-ai-checkbox').prop('checked', true);
        });

        $('#btsld-deselect-all-ai').on('click', function(e) {
            e.preventDefault();
            $('.btsld-ai-checkbox').prop('checked', false);
        });
    }

    /**
     * Clear Logs AJAX Handler
     */
    function initClearLogs() {
        $('#btsld-clear-logs-btn').on('click', function(e) {
            e.preventDefault();

            const $button = $(this);
            const $message = $('#btsld-clear-logs-message');

            if (!confirm(btsld_admin.confirm_clear)) {
                return;
            }

            $button.prop('disabled', true).text(btsld_admin.clearing);
            $message.removeClass('btsld-success btsld-error').text('');

            $.ajax({
                url: btsld_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'btsld_clear_logs',
                    nonce: btsld_admin.clear_logs_nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#btsld-blocked-count').text('0');

                        // Remove tables and pagination
                        $('.btsld-table-responsive, .btsld-pagination').remove();

                        $message.addClass('btsld-success').text(response.data.message);

                        // Render empty state if not already shown
                        if ($('#btsld-empty-log-message').length === 0) {
                            $('#btsld-log-card').append(
                                '<div class="btsld-empty-state" id="btsld-empty-log-message">' +
                                '<span class="dashicons dashicons-shield-alt"></span>' +
                                '<p>' + btsld_admin.empty_log_msg + '</p>' +
                                '</div>'
                            );
                        }

                        // Reset charts to empty state
                        if (trafficChartInstance) {
                            trafficChartInstance.data.datasets[0].data = trafficChartInstance.data.datasets[0].data.map(() => 0);
                            trafficChartInstance.update();
                        }
                        if (donutChartInstance) {
                            donutChartInstance.data.labels = ['No Bot Hits Yet'];
                            donutChartInstance.data.datasets[0].data = [1];
                            donutChartInstance.data.datasets[0].backgroundColor = ['#e2e8f0'];
                            donutChartInstance.options.plugins.tooltip.enabled = false;
                            donutChartInstance.update();
                        }
                    } else {
                        $message.addClass('btsld-error').text(response.data.message);
                    }
                },
                error: function() {
                    $message.addClass('btsld-error').text(btsld_admin.error_msg);
                },
                complete: function() {
                    $button.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> ' + btsld_admin.clear_logs);
                }
            });
        });
    }

})(jQuery);