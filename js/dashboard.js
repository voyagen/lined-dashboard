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
            `<div class="row"><div class="col-12"><div class="alert alert-danger" role="alert">Error loading data: ${error.message}</div></div></div>`;
    }
}

function createTimelineChart() {
    const container = document.getElementById('timeline-charts-container');
    container.innerHTML = ''; // Clear existing content
    
    // Create a separate timeline chart for each IP address
    for (const ip in reputationData) {
        // Get data and status first
        const records = reputationData[ip];
        const latestRecord = records[records.length - 1];
        const currentStatus = latestRecord.status;
        const statusText = {
            'GREEN': 'Goed',
            'YELLOW': 'Matig', 
            'RED': 'Slecht'
        }[currentStatus] || currentStatus;
        
        const badgeClass = {
            'GREEN': 'bg-success',
            'YELLOW': 'bg-warning text-dark',
            'RED': 'bg-danger'
        }[currentStatus] || 'bg-secondary';
        
        // Create Bootstrap grid container for this IP's chart
        const chartWrapper = document.createElement('div');
        chartWrapper.className = 'row mb-4';
        
        const chartCol = document.createElement('div');
        chartCol.className = 'col-12';
        
        const chartContainer = document.createElement('div');
        chartContainer.className = 'bg-white rounded p-3 shadow-sm';
        chartContainer.style.minHeight = '360px';
        // Format last update datetime
        const lastUpdate = new Date(latestRecord.datetime);
        const lastUpdateFormatted = lastUpdate.toLocaleDateString('nl-NL', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
        
        chartContainer.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0 fw-bold">${ip}</h5>
                <span class="badge ${badgeClass} px-3 py-2">${statusText}</span>
            </div>
            <div id="timeline-${ip.replace(/\./g, '-')}" class="w-100" style="min-height: 280px;"></div>
            <div class="mt-2 text-muted small text-end">
                <i class="bi bi-clock"></i> Laatste update: ${lastUpdateFormatted}
            </div>
        `;
        
        chartCol.appendChild(chartContainer);
        chartWrapper.appendChild(chartCol);
        container.appendChild(chartWrapper);
        
        // Prepare timeline data for this IP - only show status changes
        const timelineData = [];
        
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
                height: 280,
                zoomType: 'x',
                pinchType: 'x',
                panning: {
                    enabled: true,
                    type: 'x'
                },
                panKey: 'shift',
                backgroundColor: 'transparent',
                style: {
                    fontFamily: 'inherit'
                },
                events: {
                    load: function() {
                        // Prevent default touch behavior on chart container
                        this.container.style.touchAction = 'pinch-zoom pan-x';
                    }
                }
            },
            responsive: {
                rules: [
                    {
                        condition: { maxWidth: 576 },  // Mobile phones
                        chartOptions: {
                            chart: { 
                                height: 350,
                                pinchType: 'x',
                                panning: {
                                    enabled: true,
                                    type: 'x'
                                }
                            },
                            title: { 
                                style: { fontSize: '1em' }
                            },
                            subtitle: { 
                                text: 'Knijp om in te zoomen, sleep om te pannen',
                                style: { fontSize: '0.75em' }
                            },
                            plotOptions: {
                                timeline: {
                                    marker: { radius: 8 },
                                    dataLabels: { enabled: false }
                                }
                            }
                        }
                    },
                    {
                        condition: { maxWidth: 768, minWidth: 577 },  // Tablets
                        chartOptions: {
                            chart: { height: 320 },
                            title: {
                                style: { fontSize: '1.1em' }
                            },
                            subtitle: {
                                style: { fontSize: '0.8em' }
                            },
                            plotOptions: {
                                timeline: {
                                    marker: { radius: 7 }
                                }
                            }
                        }
                    }
                ]
            },
            title: {
                text: null
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
                        menuItems: ['viewFullscreen', 'separator', 'downloadPNG', 'downloadJPEG', 'downloadPDF', 'downloadSVG']
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
                backgroundColor: 'rgba(255, 255, 255, 0.95)',
                borderWidth: 0,
                borderRadius: 8,
                shadow: true,
                style: {
                    fontSize: '13px',
                    fontFamily: 'inherit'
                },
                formatter: function() {
                    const statusColor = statusColors[this.point.options.custom?.status] || this.point.color;
                    return `
                        <div style="
                            padding: 12px 16px;
                            background: linear-gradient(135deg, rgba(255,255,255,0.95) 0%, rgba(248,249,250,0.95) 100%);
                            border-radius: 8px;
                            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                            border: 1px solid rgba(0,0,0,0.1);
                            min-width: 220px;
                            font-family: inherit;
                        ">
                            <div style="
                                display: flex;
                                align-items: center;
                                margin-bottom: 8px;
                                padding-bottom: 8px;
                                border-bottom: 1px solid rgba(0,0,0,0.1);
                            ">
                                <div style="
                                    width: 12px;
                                    height: 12px;
                                    border-radius: 50%;
                                    background: ${statusColor};
                                    margin-right: 8px;
                                    box-shadow: 0 0 0 2px rgba(255,255,255,0.8);
                                "></div>
                                <strong style="color: #2c3e50; font-size: 14px;">${ip}</strong>
                            </div>
                            
                            <div style="margin-bottom: 8px;">
                                <div style="
                                    display: flex;
                                    justify-content: space-between;
                                    margin-bottom: 4px;
                                    font-size: 12px;
                                ">
                                    <span style="color: #7f8c8d; font-weight: 500;">Datum:</span>
                                    <span style="color: #2c3e50; font-weight: 600;">${Highcharts.dateFormat('%d/%m/%Y %H:%M', this.x)}</span>
                                </div>
                                <div style="
                                    display: flex;
                                    justify-content: space-between;
                                    margin-bottom: 8px;
                                    font-size: 12px;
                                ">
                                    <span style="color: #7f8c8d; font-weight: 500;">Status:</span>
                                    <span style="
                                        color: ${statusColor};
                                        font-weight: 700;
                                        text-transform: uppercase;
                                        font-size: 11px;
                                        padding: 2px 6px;
                                        background: rgba(${statusColor === '#27ae60' ? '39,174,96' : statusColor === '#f39c12' ? '243,156,18' : '231,76,60'}, 0.1);
                                        border-radius: 4px;
                                    ">${this.point.name}</span>
                                </div>
                            </div>
                            
                            <div style="
                                background: rgba(52,73,94,0.05);
                                padding: 8px;
                                border-radius: 4px;
                                font-size: 12px;
                                line-height: 1.4;
                                color: #34495e;
                            ">
                                ${this.point.description}
                            </div>
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
    document.getElementById('timeline-charts-container').innerHTML = '<div class="row"><div class="col-12"><div class="d-flex justify-content-center align-items-center py-5 flex-column flex-sm-row"><div class="spinner-border me-0 me-sm-2 mb-2 mb-sm-0" role="status"><span class="visually-hidden">Loading...</span></div><span class="text-muted">Laden van gegevens...</span></div></div></div>';
    
    // Load data and create charts
    loadData();
});