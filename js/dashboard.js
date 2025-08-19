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
        
        // Create timeline visualizations
        createTimelineChart();
        
    } catch (error) {
        console.error('Error loading data:', error);
        document.getElementById('timeline-charts-container').innerHTML = 
            `<div class="error">Error loading data: ${error.message}</div>`;
    }
}

function createTimelineChart() {
    const container = document.getElementById('timeline-charts-container');
    container.innerHTML = ''; // Clear existing content
    
    // Create a separate timeline chart for each IP address
    for (const ip in reputationData) {
        // Create container for this IP's chart
        const chartContainer = document.createElement('div');
        chartContainer.className = 'chart-container';
        chartContainer.innerHTML = `<div id="timeline-${ip.replace(/\./g, '-')}" style="min-height: 300px;"></div>`;
        container.appendChild(chartContainer);
        
        // Prepare timeline data for this IP - only show status changes
        const timelineData = [];
        const records = reputationData[ip];
        
        for (let i = 0; i < records.length; i++) {
            const record = records[i];
            const previousRecord = i > 0 ? records[i - 1] : null;
            
            // Only add to timeline if this is the first record or status changed
            if (!previousRecord || record.status !== previousRecord.status) {
                const statusText = {
                    'GREEN': 'Goed',
                    'YELLOW': 'Matig', 
                    'RED': 'Slecht'
                }[record.status] || record.status;
                
                timelineData.push({
                    x: new Date(record.datetime).getTime(),
                    name: statusText,
                    description: `Emails verzonden: ${parseInt(record.mails_send).toLocaleString()}<br/>
                                 Emails afgeleverd: ${parseInt(record.mails_delivered).toLocaleString()}<br/>
                                 Klacht rate: ${parseFloat(record.complain_rate).toFixed(2)}%`,
                    color: statusColors[record.status],
                    dataLabels: {
                        color: statusColors[record.status]
                    }
                });
            }
        }

        // Create timeline chart for this IP
        Highcharts.chart(`timeline-${ip.replace(/\./g, '-')}`, {
            chart: {
                type: 'timeline',
                height: 300,
                zoomType: 'x',
                panning: {
                    enabled: true,
                    type: 'x'
                },
                panKey: 'shift'
            },
            title: {
                text: `Timeline: ${ip}`,
                style: {
                    fontSize: '1.2em',
                    fontWeight: 'bold'
                }
            },
            subtitle: {
                text: 'Reputatie gebeurtenissen over tijd (Sleep om in te zoomen, Shift+Sleep om te pannen)'
            },
            xAxis: {
                type: 'datetime',
                title: {
                    text: 'Tijd'
                },
                events: {
                    afterSetExtremes: function(e) {
                        // Optional: Add custom behavior after zoom
                    }
                }
            },
            yAxis: {
                gridLineWidth: 1,
                title: null,
                labels: {
                    enabled: false
                }
            },
            legend: {
                enabled: false
            },
            exporting: {
                buttons: {
                    contextButton: {
                        menuItems: ['viewFullscreen', 'separator', 'downloadPNG', 'downloadJPEG', 'downloadPDF', 'downloadSVG', 'separator', 'resetZoom']
                    }
                }
            },
            plotOptions: {
                timeline: {
                    colorByPoint: true,
                    marker: {
                        symbol: 'circle',
                        radius: 6
                    },
                    dataLabels: {
                        enabled: false,
                    }
                    
                }
            },
            tooltip: {
                useHTML: true,
                formatter: function() {
                    return `
                        <div style="padding: 8px;">
                            <strong>${ip}</strong><br/>
                            <strong>Datum:</strong> ${Highcharts.dateFormat('%d-%m-%Y %H:%M', this.x)}<br/>
                            <strong>Status:</strong> ${this.point.name}<br/>
                            <br/>
                            ${this.point.description}
                        </div>
                    `;
                }
            },
            series: [{
                name: ip,
                data: timelineData
            }]
        });
    }
}

// Initialize dashboard
document.addEventListener('DOMContentLoaded', function() {
    // Show loading state
    document.getElementById('timeline-charts-container').innerHTML = '<div class="loading">Laden van gegevens...</div>';
    
    // Load data and create charts
    loadData();
});