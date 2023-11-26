
(function( root, $, undefined ) {
    "use strict";

    $(function () {

        const ctx = document.getElementById('numberSignIns');
        const labels = makeMember.stats.labels;
        const data = makeMember.stats.data;
        new Chart(ctx, {
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


    function restoreLayer2() {
        console.log(ctx.getDatasetMeta(1).hidden);
        ctx.setDatasetVisibility(1, true);
        ctx.update();
      }
    
      function removeLayer2() {
        console.log(ctx.getDatasetMeta(1).hidden);
        ctx.setDatasetVisibility(1, ctx.getDatasetMeta(1).hidden);
        ctx.update();
      }


} ( this, jQuery ));
