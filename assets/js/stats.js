(function (root, $, undefined) {
    "use strict";

    $(function () {
        const ctx = document.getElementById('numberSignIns');
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

    // Use submit event for the filter form
    $(document).on('submit', '#heatmapFilters', function(e) {
        e.preventDefault();
        var badge = $('#badgeFilter').val();
        var start_date = $('#startDate').val();
        var end_date = $('#endDate').val();
        var container = $('#signInHeatMap');

        $.ajax({
            url : makeMember.ajax_url,
            type : 'post',
            data : {
                action : 'makesf_heatmap',
                badge : badge,
                start_date: start_date,
                end_date: end_date,
                nonce: makeMember.nonce
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

}(this, jQuery));
