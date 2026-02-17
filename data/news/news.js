document.addEventListener('DOMContentLoaded', () => {
    const newsContainer = document.getElementById('news-container');

    fetch('data/news.json')
        .then(response => response.json())
        .then(news => {
            news.forEach(article => {
                const articleDiv = document.createElement('div');
                articleDiv.classList.add('news-article');

                const titleElement = document.createElement('h2');
                titleElement.textContent = article.title;

                const dateElement = document.createElement('p');
                dateElement.classList.add('date');
                const formattedDate = formatDate(article.date);
                dateElement.textContent = formattedDate;

                if (article.image) {
                    const imageElement = document.createElement('img');
                    imageElement.src = article.image;
                    imageElement.alt = article.title;
                    imageElement.classList.add('news-image');
                    articleDiv.appendChild(imageElement);
                }

                const contentElement = document.createElement('p');
                contentElement.classList.add('content');
                contentElement.textContent = article.content;

                articleDiv.appendChild(titleElement);
                articleDiv.appendChild(dateElement);
                articleDiv.appendChild(contentElement);

                newsContainer.appendChild(articleDiv);
            });
        })
        .catch(error => {
            console.error('Fehler beim Laden der Nachrichten:', error);
            newsContainer.textContent = 'Fehler beim Laden der Nachrichten.';
        });
});

function formatDate(dateString) {
    const date = new Date(dateString);
    const day = date.getDate().toString().padStart(2, '0');
    const month = (date.getMonth() + 1).toString().padStart(2, '0');
    const year = date.getFullYear();
    return `${day}.${month}.${year}`;
}