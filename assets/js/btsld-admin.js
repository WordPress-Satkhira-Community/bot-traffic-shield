/**
 * Bot Traffic Shield - Admin Dashboard & Chart Engine
 * Version: 2.1.3
 */
(function($) {
    'use strict';

    let trafficChartInstance = null;
    let donutChartInstance = null;
    let currentRange = '7';
    let currentMetric = 'requests'; // 'requests' or 'bandwidth'

    $(document).ready(function() {
        initTabs();
        initCharts();
        initMetricSelector();
        initTimeframeSelector();
        initLiveRefresh();
        initLogNavigation();
        initBulkAiActions();
        initBulkSeActions();
        initSearchEngineConfirm();
        initClearLogs();
    });

    /**
     * Tab Navigation Controller
     */
    function initTabs() {
        const $tabs = $('.btsld-tabs-nav .nav-tab[href^="#"]');
        const $contents = $('.btsld-tab-content');

        // Handle URL hash on initial page load (with fallback for old hash anchors)
        let hash = window.location.hash;
        if (hash === '#settings') hash = '#tab-settings';
        if (hash === '#log') hash = '#tab-log';
        if (hash === '#tab-ai-crawlers' || hash === '#blocked-bots') {
            window.location.assign('admin.php?page=bot-traffic-shield-policies&tab=ai-policies');
            return;
        }
        if (hash === '#tab-search-engines') {
            window.location.assign('admin.php?page=bot-traffic-shield-policies&tab=search-engines');
            return;
        }

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
     * Jump smoothly to the Activity Log tab from dashboard links
     */
    function initLogNavigation() {
        $(document).on('click', '.btsld-nav-to-log', function(e) {
            e.preventDefault();
            const $logTab = $('.btsld-tabs-nav .nav-tab[href="#tab-log"]');
            if ($logTab.length) {
                $logTab.trigger('click');
                $('html, body').animate({
                    scrollTop: $('#tab-log').offset().top - 80
                }, 250);
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

        // 1. Line Chart: Blocked Traffic & Bandwidth Trend
        const ctxTraffic = document.getElementById('btsldTrafficChart');
        if (ctxTraffic) {
            const ctx = ctxTraffic.getContext('2d');
            const isBandwidth = currentMetric === 'bandwidth';

            const gradientBlue = ctx.createLinearGradient(0, 0, 0, 280);
            gradientBlue.addColorStop(0, 'rgba(59, 130, 246, 0.40)');
            gradientBlue.addColorStop(0.7, 'rgba(59, 130, 246, 0.08)');
            gradientBlue.addColorStop(1, 'rgba(59, 130, 246, 0.00)');

            const gradientEmerald = ctx.createLinearGradient(0, 0, 0, 280);
            gradientEmerald.addColorStop(0, 'rgba(16, 185, 129, 0.40)');
            gradientEmerald.addColorStop(0.7, 'rgba(16, 185, 129, 0.08)');
            gradientEmerald.addColorStop(1, 'rgba(16, 185, 129, 0.00)');

            const currentData = isBandwidth ? (dataSet.bandwidth_series || []) : (dataSet.series || []);
            const currentLabel = isBandwidth ? (btsld_admin.label_bandwidth || 'Bandwidth Saved (MB)') : (btsld_admin.label_requests || 'Blocked Requests');
            const borderColor = isBandwidth ? '#10b981' : '#3b82f6';
            const pointBorderColor = isBandwidth ? '#059669' : '#2563eb';
            const activeGradient = isBandwidth ? gradientEmerald : gradientBlue;

            trafficChartInstance = new Chart(ctxTraffic, {
                type: 'line',
                data: {
                    labels: dataSet.labels,
                    datasets: [{
                        label: currentLabel,
                        data: currentData,
                        borderColor: borderColor,
                        backgroundColor: activeGradient,
                        borderWidth: 2.5,
                        fill: true,
                        tension: 0.36,
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: pointBorderColor,
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6.5,
                        pointHoverBackgroundColor: pointBorderColor,
                        pointHoverBorderColor: '#ffffff',
                        pointHoverBorderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(15, 23, 42, 0.95)',
                            titleFont: { size: 12, weight: '700', family: 'system-ui, -apple-system, sans-serif' },
                            bodyFont: { size: 12, family: 'system-ui, -apple-system, sans-serif' },
                            padding: 12,
                            boxPadding: 6,
                            cornerRadius: 8,
                            borderColor: 'rgba(255, 255, 255, 0.1)',
                            borderWidth: 1,
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    const val = context.parsed.y;
                                    const index = context.dataIndex;
                                    const reqCount = (dataSet.series && dataSet.series[index] !== undefined) ? dataSet.series[index] : val;
                                    const bwMB = (dataSet.bandwidth_series && dataSet.bandwidth_series[index] !== undefined) ? dataSet.bandwidth_series[index] : (reqCount * 2);

                                    if (currentMetric === 'bandwidth') {
                                        return [
                                            ' ⚡ ' + val.toLocaleString() + ' MB ' + (btsld_admin.unit_bandwidth || 'saved'),
                                            ' 🛡️ ' + reqCount.toLocaleString() + ' ' + (btsld_admin.unit_requests || 'bots blocked')
                                        ];
                                    } else {
                                        return [
                                            ' 🛡️ ' + val.toLocaleString() + ' ' + (btsld_admin.unit_requests || 'bots blocked'),
                                            ' ⚡ ~' + bwMB.toLocaleString() + ' MB ' + (btsld_admin.unit_bandwidth || 'saved')
                                        ];
                                    }
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: isBandwidth ? 1 : 0,
                                font: { size: 11, family: 'system-ui, -apple-system, sans-serif' },
                                color: '#64748b',
                                callback: function(value) {
                                    if (isBandwidth) {
                                        return value >= 1000 ? (value / 1024).toFixed(1) + ' GB' : value + ' MB';
                                    }
                                    return value.toLocaleString();
                                }
                            },
                            grid: {
                                color: 'rgba(226, 232, 240, 0.7)',
                                borderDash: [4, 4],
                                drawBorder: false
                            }
                        },
                        x: {
                            ticks: {
                                font: { size: 11, family: 'system-ui, -apple-system, sans-serif' },
                                color: '#64748b'
                            },
                            grid: {
                                display: false,
                                drawBorder: false
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
            const totalHits = botCounts.reduce((a, b) => a + b, 0);
            const hasData = botCounts.length > 0 && totalHits > 0;

            const chartLabels = hasData ? botKeys : ['No Bot Hits Yet'];
            const chartDataValues = hasData ? botCounts : [1];
            const modernPalette = ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4', '#6366f1', '#64748b'];
            const chartColors = hasData ? modernPalette.slice(0, chartLabels.length) : ['#e2e8f0'];

            // Center Text Plugin
            const centerTextPlugin = {
                id: 'btsldCenterTextPlugin',
                beforeDraw: function(chart) {
                    const width = chart.width;
                    const height = chart.chartArea ? (chart.chartArea.top + chart.chartArea.bottom) / 2 : chart.height / 2;
                    const ctx = chart.ctx;
                    ctx.save();

                    const currentSet = btsld_admin.chartData[currentRange] || dataSet;
                    const currentTotals = Object.values(currentSet.bot_totals || {});
                    const sum = currentTotals.reduce((a, b) => a + b, 0);
                    const activeHasData = sum > 0;

                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';

                    // Number
                    ctx.font = '700 20px system-ui, -apple-system, sans-serif';
                    ctx.fillStyle = '#0f172a';
                    const mainText = activeHasData ? sum.toLocaleString() : '0';
                    ctx.fillText(mainText, width / 2, height - 7);

                    // Label
                    ctx.font = '600 10px system-ui, -apple-system, sans-serif';
                    ctx.fillStyle = '#64748b';
                    const subText = activeHasData ? 'HITS' : 'PROTECTED';
                    ctx.fillText(subText, width / 2, height + 13);

                    ctx.restore();
                }
            };

            donutChartInstance = new Chart(ctxDonut, {
                type: 'doughnut',
                data: {
                    labels: chartLabels,
                    datasets: [{
                        data: chartDataValues,
                        backgroundColor: chartColors,
                        borderWidth: 2,
                        borderColor: '#ffffff',
                        hoverOffset: 6
                    }]
                },
                plugins: [centerTextPlugin],
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '72%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 10,
                                boxHeight: 10,
                                borderRadius: 3,
                                useBorderRadius: true,
                                padding: 10,
                                font: { size: 11, family: 'system-ui, -apple-system, sans-serif' },
                                color: '#475569'
                            }
                        },
                        tooltip: {
                            enabled: hasData,
                            backgroundColor: 'rgba(15, 23, 42, 0.95)',
                            padding: 10,
                            cornerRadius: 8,
                            borderColor: 'rgba(255, 255, 255, 0.1)',
                            borderWidth: 1,
                            callbacks: {
                                label: function(context) {
                                    const val = context.parsed;
                                    const pct = totalHits > 0 ? ((val / totalHits) * 100).toFixed(1) : '0';
                                    return ' ' + context.label + ': ' + val.toLocaleString() + ' hits (' + pct + '%)';
                                }
                            }
                        }
                    }
                }
            });
        }
    }

    /**
     * Metric Switcher (Blocked Requests vs Bandwidth Saved)
     */
    function initMetricSelector() {
        $('.btsld-metric-btn').on('click', function(e) {
            e.preventDefault();
            const metric = $(this).data('metric');
            if (metric === currentMetric) return;

            $('.btsld-metric-btn').removeClass('active');
            $(this).addClass('active');

            currentMetric = metric;
            updateLineChart();
        });
    }

    /**
     * Update Line Chart based on current range and metric
     */
    function updateLineChart() {
        if (!trafficChartInstance || !btsld_admin.chartData) return;

        const dataSet = btsld_admin.chartData[currentRange] || btsld_admin.chartData['7'];
        const isBandwidth = currentMetric === 'bandwidth';
        const ctx = trafficChartInstance.ctx;

        const gradientBlue = ctx.createLinearGradient(0, 0, 0, 280);
        gradientBlue.addColorStop(0, 'rgba(59, 130, 246, 0.40)');
        gradientBlue.addColorStop(0.7, 'rgba(59, 130, 246, 0.08)');
        gradientBlue.addColorStop(1, 'rgba(59, 130, 246, 0.00)');

        const gradientEmerald = ctx.createLinearGradient(0, 0, 0, 280);
        gradientEmerald.addColorStop(0, 'rgba(16, 185, 129, 0.40)');
        gradientEmerald.addColorStop(0.7, 'rgba(16, 185, 129, 0.08)');
        gradientEmerald.addColorStop(1, 'rgba(16, 185, 129, 0.00)');

        const currentData = isBandwidth ? (dataSet.bandwidth_series || []) : (dataSet.series || []);
        const currentLabel = isBandwidth ? (btsld_admin.label_bandwidth || 'Bandwidth Saved (MB)') : (btsld_admin.label_requests || 'Blocked Requests');
        const borderColor = isBandwidth ? '#10b981' : '#3b82f6';
        const pointBorderColor = isBandwidth ? '#059669' : '#2563eb';
        const activeGradient = isBandwidth ? gradientEmerald : gradientBlue;

        trafficChartInstance.data.labels = dataSet.labels;
        trafficChartInstance.data.datasets[0].label = currentLabel;
        trafficChartInstance.data.datasets[0].data = currentData;
        trafficChartInstance.data.datasets[0].borderColor = borderColor;
        trafficChartInstance.data.datasets[0].backgroundColor = activeGradient;
        trafficChartInstance.data.datasets[0].pointBorderColor = pointBorderColor;
        trafficChartInstance.data.datasets[0].pointHoverBackgroundColor = pointBorderColor;

        trafficChartInstance.options.scales.y.ticks.precision = isBandwidth ? 1 : 0;
        trafficChartInstance.options.scales.y.ticks.callback = function(value) {
            if (isBandwidth) {
                return value >= 1024 ? (value / 1024).toFixed(1) + ' GB' : value + ' MB';
            }
            return value.toLocaleString();
        };

        trafficChartInstance.update();
    }

    /**
     * Update Donut Chart based on current range
     */
    function updateDonutChart() {
        if (!donutChartInstance || !btsld_admin.chartData) return;

        const dataSet = btsld_admin.chartData[currentRange] || btsld_admin.chartData['7'];
        const botKeys = Object.keys(dataSet.bot_totals || {});
        const botCounts = Object.values(dataSet.bot_totals || {});
        const totalHits = botCounts.reduce((a, b) => a + b, 0);
        const hasData = botCounts.length > 0 && totalHits > 0;
        const modernPalette = ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4', '#6366f1', '#64748b'];

        if (hasData) {
            donutChartInstance.data.labels = botKeys;
            donutChartInstance.data.datasets[0].data = botCounts;
            donutChartInstance.data.datasets[0].backgroundColor = modernPalette.slice(0, botKeys.length);
            donutChartInstance.options.plugins.tooltip.enabled = true;
            donutChartInstance.options.plugins.tooltip.callbacks.label = function(context) {
                const val = context.parsed;
                const pct = totalHits > 0 ? ((val / totalHits) * 100).toFixed(1) : '0';
                return ' ' + context.label + ': ' + val.toLocaleString() + ' hits (' + pct + '%)';
            };
        } else {
            donutChartInstance.data.labels = ['No Bot Hits Yet'];
            donutChartInstance.data.datasets[0].data = [1];
            donutChartInstance.data.datasets[0].backgroundColor = ['#e2e8f0'];
            donutChartInstance.options.plugins.tooltip.enabled = false;
        }

        donutChartInstance.update();
    }

    /**
     * Update Period Stats Summary Bar inside the chart header
     */
    function updatePeriodSummaryBar(dataSet) {
        if (!dataSet) return;

        const totalReq = dataSet.total_period_requests !== undefined ? dataSet.total_period_requests : (dataSet.series || []).reduce((a, b) => a + b, 0);
        const dailyAvg = dataSet.daily_avg_requests !== undefined ? dataSet.daily_avg_requests : (totalReq / parseInt(currentRange, 10)).toFixed(1);
        const totalBw = dataSet.total_period_bandwidth || ((totalReq * 2).toFixed(1) + ' MB');
        const peakLabel = dataSet.peak_day_label ? (dataSet.peak_day_label + ' (' + (dataSet.peak_day_count || 0).toLocaleString() + ')') : 'None';

        $('#btsld-sum-total').text(totalReq.toLocaleString() + ' requests');
        $('#btsld-sum-avg').text(dailyAvg + ' / day');
        $('#btsld-sum-bw').text(totalBw);
        $('#btsld-sum-peak').text(peakLabel);
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
            updateLineChart();
            updateDonutChart();
            updatePeriodSummaryBar(dataSet);
        });
    }

    /**
     * Live Dashboard Refresh via AJAX
     */
    function initLiveRefresh() {
        $('#btsld-refresh-stats-btn').on('click', function(e) {
            e.preventDefault();
            const $btn = $(this);
            const $label = $btn.find('.btsld-refresh-label');

            if ($btn.hasClass('is-loading')) return;

            $btn.addClass('is-loading');
            $label.text(btsld_admin.refreshing || 'Refreshing...');

            $.ajax({
                url: btsld_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'btsld_refresh_dashboard',
                    nonce: btsld_admin.refresh_nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        const d = response.data;

                        // 1. Update KPI Values
                        $('#btsld-blocked-count').text(d.total_blocked);
                        $('#btsld-trend-today').text('+' + d.blocked_today + ' today');
                        $('#btsld-bw-total').text(d.bandwidth_saved_total);
                        $('#btsld-bw-today-badge').text('+' + d.bandwidth_saved_today + ' today');
                        $('#btsld-queries-saved').text(d.queries_saved_total);
                        $('#btsld-blocked-today').text(d.blocked_today);
                        $('#btsld-active-rules').text(d.active_ai_count + ' / ' + d.registered_bots_total);
                        const rulesPct = d.registered_bots_total > 0 ? Math.round((d.active_ai_count / d.registered_bots_total) * 100) : 100;
                        $('#btsld-rules-pct').text(rulesPct + '% Armed');
                        $('#btsld-top-bot').text(d.top_bot_name);
                        $('#btsld-top-bot-provider').text(d.top_bot_provider);
                        $('#btsld-top-bot-share').text(d.top_bot_percent > 0 ? (d.top_bot_percent + '% of blocked traffic') : 'No crawler hits recorded');

                        // 2. Update Ribbon
                        $('#btsld-ribbon-bw').text(d.bandwidth_saved_total);
                        $('#btsld-ribbon-queries').text(d.queries_saved_total);

                        // 3. Update Chart Datasets
                        if (d.chart_data) {
                            btsld_admin.chartData = d.chart_data;
                            updateLineChart();
                            updateDonutChart();
                            updatePeriodSummaryBar(btsld_admin.chartData[currentRange]);
                        }

                        // 4. Update Leaderboard
                        if (d.leaderboard && d.leaderboard.length) {
                            let lbHtml = '';
                            d.leaderboard.forEach(function(item, idx) {
                                lbHtml += '<div class="btsld-leaderboard-row">';
                                lbHtml += '<div class="btsld-leaderboard-meta">';
                                lbHtml += '<span class="btsld-leaderboard-rank">#' + (idx + 1) + '</span>';
                                lbHtml += '<span class="btsld-leaderboard-name" title="' + escapeHtml(item.name) + '">' + escapeHtml(item.name) + '</span>';
                                lbHtml += '<span class="btsld-provider-pill">' + escapeHtml(item.provider) + '</span>';
                                lbHtml += '<span class="btsld-leaderboard-count">' + item.count + ' (' + item.percent + '%)</span>';
                                lbHtml += '</div>';
                                lbHtml += '<div class="btsld-progress-track">';
                                lbHtml += '<div class="btsld-progress-bar bar-rank-' + Math.min(5, idx + 1) + '" style="width: ' + item.percent + '%;"></div>';
                                lbHtml += '</div>';
                                lbHtml += '</div>';
                            });
                            $('#btsld-leaderboard-list').html(lbHtml);
                        }

                        // 5. Update Recent Feed Table
                        if (d.recent_events && d.recent_events.length) {
                            let feedHtml = '';
                            d.recent_events.forEach(function(ev) {
                                feedHtml += '<tr>';
                                feedHtml += '<td class="btsld-time-col">';
                                feedHtml += '<span class="btsld-time-ago">' + escapeHtml(ev.time_ago) + '</span>';
                                feedHtml += '<span class="btsld-time-exact">' + escapeHtml(ev.time_exact) + '</span>';
                                feedHtml += '</td>';
                                feedHtml += '<td>';
                                feedHtml += '<span class="btsld-bot-badge">' + escapeHtml(ev.bot) + '</span> ';
                                feedHtml += '<span class="btsld-provider-pill">' + escapeHtml(ev.provider) + '</span>';
                                feedHtml += '</td>';
                                feedHtml += '<td><code class="btsld-ip-code">' + escapeHtml(ev.ip) + '</code></td>';
                                feedHtml += '<td class="btsld-ua-col" title="' + escapeHtml(ev.ua) + '"><span class="btsld-ua-text">' + escapeHtml(ev.ua) + '</span></td>';
                                feedHtml += '<td style="text-align: center;"><span class="btsld-action-pill status-blocked">403 Blocked</span></td>';
                                feedHtml += '</tr>';
                            });

                            if ($('#btsld-recent-feed-tbody').length) {
                                $('#btsld-recent-feed-tbody').html(feedHtml);
                            }
                        }

                        $label.text(btsld_admin.refreshed || 'Updated Just Now');
                        setTimeout(function() {
                            $label.text('Refresh Stats');
                        }, 2000);
                    }
                },
                complete: function() {
                    $btn.removeClass('is-loading');
                }
            });
        });
    }

    /**
     * Escape HTML helper
     */
    function escapeHtml(string) {
        if (!string) return '';
        return String(string)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    /**
     * AI Crawler Bulk Actions (Select All / Deselect All)
     */
    function initBulkAiActions() {
        // Free AI crawlers bulk actions
        $('#btsld-select-all-ai').on('click', function(e) {
            e.preventDefault();
            $('.btsld-ai-checkbox:not(.btsld-pro-ai-checkbox)').prop('checked', true);
        });

        $('#btsld-deselect-all-ai').on('click', function(e) {
            e.preventDefault();
            $('.btsld-ai-checkbox:not(.btsld-pro-ai-checkbox)').prop('checked', false);
        });

        // Pro AI policies bulk actions (when unlocked)
        $('#btsld-select-all-pro-ai').on('click', function(e) {
            e.preventDefault();
            $('.btsld-pro-ai-checkbox').prop('checked', true);
        });

        $('#btsld-deselect-all-pro-ai').on('click', function(e) {
            e.preventDefault();
            $('.btsld-pro-ai-checkbox').prop('checked', false);
        });
    }

    /**
     * Bulk allow / block for regional search engines
     */
    function initBulkSeActions() {
        $('#btsld-select-all-se').on('click', function(e) {
            e.preventDefault();
            $('.btsld-se-checkbox').prop('checked', true);
        });

        $('#btsld-deselect-all-se').on('click', function(e) {
            e.preventDefault();
            if (!confirm(btsld_admin.confirm_disable_se.replace('%s', 'all regional / other crawlers'))) {
                return;
            }
            $('.btsld-se-checkbox').prop('checked', false);
        });
    }

    /**
     * Confirm before disabling a regional search engine (blocking its crawler).
     */
    function initSearchEngineConfirm() {
        $(document).on('change', '.btsld-se-checkbox', function() {
            var $cb = $(this);
            if ($cb.is(':checked')) {
                return;
            }
            var label = $cb.data('se-label') || 'this crawler';
            var msg = (btsld_admin.confirm_disable_se || 'Disabling %s will block its crawler from indexing your site — only do this if you do not need that traffic. Continue?').replace('%s', label);
            if (!confirm(msg)) {
                $cb.prop('checked', true);
            }
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
                        $('#btsld-blocked-count, #btsld-blocked-today').text('0');
                        $('#btsld-bw-total, #btsld-bw-today-badge, #btsld-ribbon-bw').text('0 MB');
                        $('#btsld-queries-saved, #btsld-ribbon-queries').text('0');
                        $('#btsld-trend-today').text('+0 today');
                        $('#btsld-top-bot').text('None');
                        $('#btsld-top-bot-provider').text('Crawler / Scraper');
                        $('#btsld-top-bot-share').text('No crawler hits recorded');

                        // Remove tables and pagination in activity log
                        $('.btsld-table-responsive, .btsld-pagination').remove();

                        $message.addClass('btsld-success').text(response.data.message);

                        // Render empty state if not already shown in log tab
                        if ($('#btsld-empty-log-message').length === 0) {
                            $('#btsld-log-card').append(
                                '<div class="btsld-empty-state" id="btsld-empty-log-message">' +
                                '<span class="dashicons dashicons-shield-alt"></span>' +
                                '<p>' + btsld_admin.empty_log_msg + '</p>' +
                                '</div>'
                            );
                        }

                        // Empty leaderboard
                        $('#btsld-leaderboard-list').html('<p class="btsld-no-data-hint">No scraper activity recorded yet.</p>');

                        // Empty recent feed
                        $('#btsld-recent-feed-container').html(
                            '<div class="btsld-empty-state">' +
                            '<span class="dashicons dashicons-shield"></span>' +
                            '<h4>No Bot Traffic Detected Yet</h4>' +
                            '<p>Your website is actively protected. Incoming bot and scraper requests will appear here in real-time as they are intercepted.</p>' +
                            '</div>'
                        );

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

                        // Reset summary bar
                        $('#btsld-sum-total').text('0 requests');
                        $('#btsld-sum-avg').text('0 / day');
                        $('#btsld-sum-bw').text('0 MB');
                        $('#btsld-sum-peak').text('None');
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
