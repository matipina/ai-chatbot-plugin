document.addEventListener('DOMContentLoaded', function() {
    var ctx = document.getElementById('sessionsChart').getContext('2d');
    var sessionsData = <?php echo json_encode(get_sessions_data_last_7_days()); ?>;
    var chart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: Object.keys(sessionsData), // Use the keys from sessionsData as labels
            datasets: [{
                label: 'Sessions',
                data: Object.values(sessionsData), // Use the values from sessionsData as data points
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
});
