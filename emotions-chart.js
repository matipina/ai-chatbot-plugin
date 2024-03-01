document.addEventListener('DOMContentLoaded', function() {
    var ctx = document.getElementById('emotionsChart').getContext('2d');

    // Check if aiChatbotEmotionData is defined
    if (typeof aiChatbotEmotionData === 'undefined') {
        console.error('aiChatbotEmotionData is not defined.');
        return;
    }

    // Check if aiChatbotEmotionData.emotions is defined
    if (typeof aiChatbotEmotionData.emotions === 'undefined') {
        console.error('aiChatbotEmotionData.emotions is not defined.');
        return;
    }

    // Prepare data for the Chart
    var data = {
        labels: aiChatbotEmotionData.labels,
        datasets: [
            {
                label: 'Happiness',
                data: aiChatbotEmotionData.emotions.happiness,
                borderColor: 'rgba(255, 206, 86, 1)',
                backgroundColor: 'rgba(255, 206, 86, 0.2)',
            },
            {
                label: 'Sadness',
                data: aiChatbotEmotionData.emotions.sadness,
                borderColor: 'rgba(54, 162, 235, 1)',
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
            },
            {
                label: 'Anger',
                data: aiChatbotEmotionData.emotions.anger,
                borderColor: 'rgba(255, 99, 132, 1)',
                backgroundColor: 'rgba(255, 99, 132, 0.2)',
            },
            {
                label: 'Fear',
                data: aiChatbotEmotionData.emotions.fear,
                borderColor: 'rgba(153, 102, 255, 1)',
                backgroundColor: 'rgba(153, 102, 255, 0.2)',
            },
            {
                label: 'Neutral',
                data: aiChatbotEmotionData.emotions.neutral,
                borderColor: 'rgba(201, 203, 207, 1)',
                backgroundColor: 'rgba(201, 203, 207, 0.2)',
            }
        ]
    };

    var config = {
        type: 'line', // Change to 'bar' for a bar chart, if desired
        data: data,
        options: {
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    };

    new Chart(ctx, config);
});