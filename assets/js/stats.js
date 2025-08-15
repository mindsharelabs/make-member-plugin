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
    });

    $(document).on('change', '#heatmapFilters', function() {
        var days = $('#daysFilter').val();
        var badge = $('#badgeFilter').val();
        var container = $('#signInHeatMap');
        
        $.ajax({
            url : makeMember.ajax_url,
            type : 'post',
            data : {
                action : 'makesf_heatmap',
                days : days,
                badge : badge,
            },
            beforeSend: function() {
                container.addClass('loading');
                container.html('<div class="loading">Loading...</div>');
            },
            success: function(response) {
                container.html(response.data.html).removeClass('loading');
            },
            error: function (response) {
                console.log('An error occurred.');
                console.log(response);
            },
        });
    });


    function restoreLayer2() {
        ctx.setDatasetVisibility(1, true);
        ctx.update();
    }

    function removeLayer2() {
        ctx.setDatasetVisibility(1, ctx.getDatasetMeta(1).hidden);
        ctx.update();
    }


}(this, jQuery));
