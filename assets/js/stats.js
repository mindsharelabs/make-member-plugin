(function (root, $, undefined) {
    "use strict";

    $(function () {
        const ctx = document.getElementById('numberSignIns');
        const monthCont = document.getElementById('signinsHeatmap');
        const labels = makeMember.stats.labels;
        const data = makeMember.stats.data;
        var signByMonth = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: data
            },
            options: {
                scales: {
                    align: 'start',
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Use precomputed matrix from PHP (makeMember.stats.matrix)
        const MATRIX = makeMember && makeMember.stats && makeMember.stats.matrix ? makeMember.stats.matrix : {};
        const DAY_LABELS = MATRIX.dayLabels || ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        const grid = MATRIX.grid || Array.from({ length: 7 }, () => Array(24).fill(0));
        const max = typeof MATRIX.max === 'number' ? MATRIX.max : 0;

        // Flatten grid into matrix points for the Chart.js Matrix dataset
        const points = [];
        for (let day = 0; day < grid.length; day++) {
            const row = grid[day] || [];
            for (let hour = 0; hour < 24; hour++) {
                const v = row[hour] || 0;
                points.push({ x: hour, y: DAY_LABELS[day], v });
            }
        }

        function colorFor(v, max) {
            const t = max ? v / max : 0;
            const light = 240, dark = 40;
            const shade = Math.round(light + (dark - light) * t);
            return `rgb(${shade},${shade},${shade})`;
        }


        var signinsHeatmap = new Chart(monthCont, {
            type: 'matrix',
            data: {
                datasets: [{
                    label: 'Sign-ins by Day by Hour',
                    data: points,
                    borderWidth: 0.5,
                    borderColor: 'rgba(0,0,0,0.25)',
                    backgroundColor: (ctx) => colorFor((ctx.raw && typeof ctx.raw.v === 'number') ? ctx.raw.v : 0, max),
                    width: ({ chart }) => {
                        const s = chart.scales.x;
                        if (!s) return 0;
                        return (s.getPixelForValue(1) - s.getPixelForValue(0)) - 1; // -1 for gutter
                    },
                    height: ({ chart }) => {
                        const s = chart.scales.y;
                        if (!s) return 0;
                        // use adjacent day labels to compute step size
                        if (!DAY_LABELS[1]) return 0;
                        return (s.getPixelForValue(DAY_LABELS[1]) - s.getPixelForValue(DAY_LABELS[0])) - 1;
                    },
                }],
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    x: {
                        type: 'linear',
                        min: 0,
                        max: 24,
                        offset: true,
                        ticks: {
                            stepSize: 1,
                            callback: (v) => {
                                const hour = Math.floor(v);
                                const period = hour < 12 ? 'am' : 'pm';
                                const displayHour = hour % 12 === 0 ? 12 : hour % 12;
                                return `${displayHour}${period}`;
                            }
                        }
                    },
                    y: {
                        type: 'category',
                        labels: DAY_LABELS,
                        display: true,
                        offset: false
                    }
                }
            }
        });


    });





    // signinsHeatmap.canvas.parentNode.style.height = '500px';


    function restoreLayer2() {
        ctx.setDatasetVisibility(1, true);
        ctx.update();
    }

    function removeLayer2() {
        ctx.setDatasetVisibility(1, ctx.getDatasetMeta(1).hidden);
        ctx.update();
    }


}(this, jQuery));
