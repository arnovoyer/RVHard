document.addEventListener('DOMContentLoaded', () => {
    const container = document.querySelector('.related-news-list');
    if (!container) return;

    const currentSlug = window.location.pathname
        .split('/')
        .pop()
        .replace('.html', '');

    fetch('/data/News/news.json')
        .then(response => {
            if (!response.ok) throw new Error("news.json konnte nicht geladen werden");
            return response.json();
        })
        .then(newsItems => {
            const currentItem = newsItems.find(item => item.slug === currentSlug);
            if (!currentItem) {
                console.warn("Aktueller Artikel nicht in news.json gefunden.");
                return;
            }

            const currentTags = currentItem.tags || [];
            const currentDate = currentItem.date ? new Date(currentItem.date) : null;

            /* ===============================
               1️⃣ TAG-MATCHING (gewichtigt)
            =============================== */

            let relatedItems = newsItems
                .filter(item => item.slug !== currentSlug && Array.isArray(item.tags))
                .map(item => {
                    const commonTags = item.tags.filter(tag => currentTags.includes(tag));
                    return {
                        ...item,
                        tagScore: commonTags.length
                    };
                })
                .filter(item => item.tagScore > 0)
                .sort((a, b) => b.tagScore - a.tagScore);

            /* ===============================
               2️⃣ FALLBACK: ähnlicher Zeitraum
            =============================== */

            if (relatedItems.length === 0 && currentDate) {
                relatedItems = newsItems
                    .filter(item => item.slug !== currentSlug && item.date)
                    .map(item => ({
                        ...item,
                        dateDiff: Math.abs(
                            new Date(item.date) - currentDate
                        )
                    }))
                    .sort((a, b) => a.dateDiff - b.dateDiff);
            }

            /* ===============================
               3️⃣ RENDERING (unverändert)
            =============================== */

            relatedItems.slice(0, 2).forEach(item => {
                const div = document.createElement("div");
                div.className = "related-news-item";

                div.innerHTML = `
                    <a href="/news/${item.slug}.html">
                        <div class="related-news-thumb" style="background-image: url('${item.image}');"></div>
                        <div class="related-news-text">
                            <h3>${item.title}</h3>
                            <p>${item.content.substring(0, 100)}...</p>
                        </div>
                    </a>
                `;

                container.appendChild(div);
            });
        })
        .catch(err => console.error("Fehler beim Laden verwandter News:", err));
});
