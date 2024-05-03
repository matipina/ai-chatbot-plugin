document.addEventListener("DOMContentLoaded", function() {
    var ctx = document.getElementById("sessionsChart");
    if (ctx) {
        var ctxSessions = ctx.getContext("2d");
        if (window.myChartInstance) {
            window.myChartInstance.destroy();
        }
        window.myChartInstance = new Chart(ctxSessions, {
            type: "bar",
            data: ChartData, // Data from PHP
            options: {
                scales: { y: { beginAtZero: true } },
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: true } }
            }
        });
    } else {
        console.error("SessionsChart element not found");
    }
});
