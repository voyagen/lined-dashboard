<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IP Reputation Dashboard - Mailfabriek</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            margin: 0;
            font-size: 2.2em;
        }
        .subtitle {
            color: #7f8c8d;
            margin: 10px 0 0 0;
            font-size: 1.1em;
        }
        .dashboard {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }
        .chart-container {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            min-height: 400px;
        }
        .loading {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 200px;
            font-size: 1.2em;
            color: #7f8c8d;
        }
        .error {
            color: #e74c3c;
            text-align: center;
            padding: 20px;
            background-color: #fdf2f2;
            border: 1px solid #e74c3c;
            border-radius: 6px;
            margin: 20px 0;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            padding: 15px;
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-value {
            font-size: 1.8em;
            font-weight: bold;
            margin: 5px 0;
        }
        .stat-label {
            color: #7f8c8d;
            font-size: 0.9em;
        }
        .green { color: #27ae60; }
        .yellow { color: #f39c12; }
        .red { color: #e74c3c; }
    </style>
</head>
<body>
    <div class="header">
        <h1>IP Reputation Dashboard</h1>
    </div>

    <div class="dashboard">
        <div id="statistics" class="stats-grid"></div>
        
        <div class="chart-container">
            <div id="timeline-chart"></div>
        </div>
        
        <div class="chart-container">
            <div id="volume-chart"></div>
        </div>
        
        <div class="chart-container">
            <div id="distribution-chart"></div>
        </div>
    </div>

    <script src="https://code.highcharts.com/highcharts.js"></script>
    <script src="https://code.highcharts.com/modules/timeline.js"></script>
    <script>
        // Global data storage
        let reputationData = null;
        
        // Color mapping for reputation status
        const statusColors = {
            'GREEN': '#27ae60',
            'YELLOW': '#f39c12', 
            'RED': '#e74c3c'
        };

        // Status priority for timeline ordering
        const statusPriority = {
            'GREEN': 1,
            'YELLOW': 2,
            'RED': 3
        };

        // Load and process data
        async function loadData() {
            try {
                const response = await fetch('api/v1/?action=dashboard');
                const result = await response.json();
                
                if (!result.success) {
                    throw new Error(result.error);
                }
                
                reputationData = result.data.data;
                
                // Create all visualizations
                createStatistics();
                createTimelineChart();
                createVolumeChart();
                createDistributionChart();
                
            } catch (error) {
                console.error('Error loading data:', error);
                document.getElementById('timeline-chart').innerHTML = 
                    `<div class="error">Error loading data: ${error.message}</div>`;
            }
        }

        function createStatistics() {
            let totalRecords = 0;
            let statusCount = {GREEN: 0, YELLOW: 0, RED: 0};
            let totalEmailsSent = 0;
            let ipCount = 0;

            for (const ip in reputationData) {
                ipCount++;
                for (const record of reputationData[ip]) {
                    totalRecords++;
                    statusCount[record.status]++;
                    totalEmailsSent += parseInt(record.mails_send);
                }
            }

            const statsHtml = `
                <div class="stat-card">
                    <div class="stat-value">${ipCount}</div>
                    <div class="stat-label">IP Adressen</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${totalRecords}</div>
                    <div class="stat-label">Metingen</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${totalEmailsSent.toLocaleString()}</div>
                    <div class="stat-label">Emails Verzonden</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value green">${statusCount.GREEN}</div>
                    <div class="stat-label">Groen Status</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value yellow">${statusCount.YELLOW}</div>
                    <div class="stat-label">Geel Status</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value red">${statusCount.RED}</div>
                    <div class="stat-label">Rood Status</div>
                </div>
            `;

            document.getElementById('statistics').innerHTML = statsHtml;
        }

        function createTimelineChart() {
            const series = [];
            
            for (const ip in reputationData) {
                const data = reputationData[ip].map(record => {
                    return {
                        x: new Date(record.datetime).getTime(),
                        y: statusPriority[record.status],
                        status: record.status,
                        mails_send: parseInt(record.mails_send),
                        mails_delivered: parseInt(record.mails_delivered),
                        complain_rate: parseFloat(record.complain_rate),
                        color: statusColors[record.status]
                    };
                });
                
                series.push({
                    name: ip,
                    data: data,
                    marker: {
                        radius: 5,
                        symbol: 'circle'
                    }
                });
            }

            Highcharts.chart('timeline-chart', {
                chart: {
                    type: 'line',
                    height: 400,
                    zoomType: 'x'
                },
                title: {
                    text: 'IP Reputation Timeline',
                    style: {
                        fontSize: '1.4em',
                        fontWeight: 'bold'
                    }
                },
                subtitle: {
                    text: 'Reputatie ontwikkeling per IP adres over tijd'
                },
                xAxis: {
                    type: 'datetime',
                    title: {
                        text: 'Tijd'
                    }
                },
                yAxis: {
                    title: {
                        text: 'Reputation Status'
                    },
                    categories: ['', 'GREEN', 'YELLOW', 'RED'],
                    min: 0.5,
                    max: 3.5,
                    gridLineColor: '#e6e6e6'
                },
                plotOptions: {
                    line: {
                        lineWidth: 2,
                        marker: {
                            enabled: true
                        }
                    },
                    series: {
                        point: {
                            events: {
                                mouseOver: function() {
                                    this.marker.graphic.attr({
                                        r: 8
                                    });
                                },
                                mouseOut: function() {
                                    this.marker.graphic.attr({
                                        r: 5
                                    });
                                }
                            }
                        }
                    }
                },
                tooltip: {
                    useHTML: true,
                    formatter: function() {
                        return `
                            <div style="padding: 5px;">
                                <strong>${this.series.name}</strong><br/>
                                <strong>Status:</strong> <span style="color: ${this.point.color}">${this.point.status}</span><br/>
                                <strong>Datum:</strong> ${Highcharts.dateFormat('%d-%m-%Y %H:%M', this.x)}<br/>
                                <strong>Emails Verzonden:</strong> ${this.point.mails_send.toLocaleString()}<br/>
                                <strong>Emails Afgeleverd:</strong> ${this.point.mails_delivered.toLocaleString()}<br/>
                                <strong>Klacht Rate:</strong> ${this.point.complain_rate}%
                            </div>
                        `;
                    }
                },
                legend: {
                    enabled: true,
                    align: 'center',
                    verticalAlign: 'bottom'
                },
                series: series
            });
        }

        function createVolumeChart() {
            const series = [];
            
            for (const ip in reputationData) {
                const data = reputationData[ip].map(record => ({
                    x: new Date(record.datetime).getTime(),
                    y: parseInt(record.mails_send),
                    status: record.status,
                    delivered: parseInt(record.mails_delivered),
                    color: statusColors[record.status]
                }));
                
                series.push({
                    name: ip,
                    data: data,
                    type: 'column'
                });
            }

            Highcharts.chart('volume-chart', {
                chart: {
                    height: 400,
                    zoomType: 'x'
                },
                title: {
                    text: 'Email Volume per IP Address',
                    style: {
                        fontSize: '1.4em',
                        fontWeight: 'bold'
                    }
                },
                subtitle: {
                    text: 'Verzonden email volumes gekleurd naar reputatie status'
                },
                xAxis: {
                    type: 'datetime',
                    title: {
                        text: 'Tijd'
                    }
                },
                yAxis: {
                    title: {
                        text: 'Aantal Emails'
                    }
                },
                plotOptions: {
                    column: {
                        grouping: false,
                        pointPadding: 0,
                        groupPadding: 0,
                        borderWidth: 0,
                        dataLabels: {
                            enabled: false
                        }
                    }
                },
                tooltip: {
                    useHTML: true,
                    formatter: function() {
                        return `
                            <div style="padding: 5px;">
                                <strong>${this.series.name}</strong><br/>
                                <strong>Datum:</strong> ${Highcharts.dateFormat('%d-%m-%Y %H:%M', this.x)}<br/>
                                <strong>Status:</strong> <span style="color: ${this.point.color}">${this.point.status}</span><br/>
                                <strong>Verzonden:</strong> ${this.y.toLocaleString()}<br/>
                                <strong>Afgeleverd:</strong> ${this.point.delivered.toLocaleString()}
                            </div>
                        `;
                    }
                },
                series: series
            });
        }

        function createDistributionChart() {
            const statusDistribution = {};
            
            for (const ip in reputationData) {
                for (const record of reputationData[ip]) {
                    if (!statusDistribution[record.status]) {
                        statusDistribution[record.status] = 0;
                    }
                    statusDistribution[record.status]++;
                }
            }

            const pieData = Object.keys(statusDistribution).map(status => ({
                name: status,
                y: statusDistribution[status],
                color: statusColors[status]
            }));

            Highcharts.chart('distribution-chart', {
                chart: {
                    type: 'pie',
                    height: 400
                },
                title: {
                    text: 'Status Distribution',
                    style: {
                        fontSize: '1.4em',
                        fontWeight: 'bold'
                    }
                },
                subtitle: {
                    text: 'Verdeling van reputatie statussen over alle metingen'
                },
                plotOptions: {
                    pie: {
                        allowPointSelect: true,
                        cursor: 'pointer',
                        dataLabels: {
                            enabled: true,
                            format: '<b>{point.name}</b>: {point.percentage:.1f}%'
                        },
                        showInLegend: true
                    }
                },
                tooltip: {
                    pointFormat: '<b>{point.name}</b>: {point.y} metingen ({point.percentage:.1f}%)'
                },
                series: [{
                    name: 'Status',
                    colorByPoint: true,
                    data: pieData
                }]
            });
        }

        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            // Show loading state
            document.getElementById('timeline-chart').innerHTML = '<div class="loading">Laden van gegevens...</div>';
            
            // Load data and create charts
            loadData();
        });
    </script>
</body>
</html>