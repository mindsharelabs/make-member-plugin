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


    $.ajax({
        url : makeSocialSettings.ajaxurl,
        type : 'post',
        data : {
            action : action,
            destination : destination,
            eventID : eventID,
        },
        beforeSend: function() {
            container.addClass('loading');
            noticeContainer.html('<div class="notice notice-info">Sending event to ' + destination + '...</div>');
        },
        success: function(response) {
            if(response.success) {
                noticeContainer.html('<div class="notice notice-success">' + response.data.message + '</div>');
            } else {
                noticeContainer.html('<div class="notice notice-error">' + response.data.message + '</div>');
            }
            console.log(response);
        },
        error: function (response) {
            console.log('An error occurred.');
            console.log(response);
        },
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
