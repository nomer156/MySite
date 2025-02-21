document.addEventListener('DOMContentLoaded', () => {
    // Обновление лидерборда
    setInterval(() => {
        fetch('index.php')
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newLeaderboard = doc.querySelector('.leaderboard-grid');
                if (newLeaderboard) document.querySelector('.leaderboard-grid').innerHTML = newLeaderboard.innerHTML;
            });
    }, 30000);

    // Обновление чата в лобби
    const chat = document.querySelector('.chat');
    if (chat) {
        setInterval(() => {
            fetch(window.location.href)
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    chat.innerHTML = doc.querySelector('.chat').innerHTML;
                });
        }, 5000);
    }
});