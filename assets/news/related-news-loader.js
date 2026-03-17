document.addEventListener('DOMContentLoaded', () => {
    const container = document.querySelector('.related-news-list');
    if (!container) return;

    container.innerHTML = '';

    const url = new URL(window.location.href);
    const slugFromQuery = url.searchParams.get('slug');
    const currentSlug = slugFromQuery || window.location.pathname
        .split('/')
        .pop()
        .replace('.html', '');

    function normalizeNewsPayload(payload) {
        if (Array.isArray(payload)) return payload;
        if (payload && Array.isArray(payload.items)) return payload.items;
        return [];
    }

    function resolveArticleLink(item) {
        const slug = String(item.slug || '').trim();
        const external = typeof item.link === 'string' ? item.link.trim() : '';

        if (slug) return `/news/${encodeURIComponent(slug)}.html`;
        if (external && !external.toLowerCase().startsWith('externer link')) {
            if (external.startsWith('/') || /^https?:\/\//i.test(external)) return external;
        }
        return '/news.html';
    }

    Promise.all([
        fetch('/data/news/news-cms.json')
            .then(response => {
                if (response.ok) return response.json();
                return fetch('/data/news/news.json').then(fallback => {
                    if (!fallback.ok) throw new Error("news.json konnte nicht geladen werden");
                    return fallback.json();
                });
            })
    ])
        .then(([response]) => {
            const newsItems = normalizeNewsPayload(response);
            const currentItem = newsItems.find(item => item.slug === currentSlug);
            if (!currentItem) {
                console.warn("Aktueller Artikel nicht in news.json gefunden.");
                return;
            }

            const currentTags = currentItem.tags || [];
            const currentDate = currentItem.date ? new Date(currentItem.date) : null;

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

            const seen = new Set();
            relatedItems = relatedItems.filter(item => {
                const key = String(item.title || '').trim().toLowerCase();
                if (!key) return false;
                if (seen.has(key)) return false;
                seen.add(key);
                return true;
            });


            relatedItems.slice(0, 2).forEach(item => {
                const div = document.createElement("div");
                div.className = "related-news-item";
                const href = resolveArticleLink(item);
                const thumbStyle = item.image ? ` style="background-image: url('${item.image}');"` : '';
                const teaser = String(item.content || '').trim();
                const teaserText = teaser.length > 120 ? `${teaser.slice(0, 120)}…` : teaser;

                div.innerHTML = `
                    <a href="${href}">
                        <div class="related-news-thumb"${thumbStyle}></div>
                        <div class="related-news-text">
                            <h3>${item.title}</h3>
                            ${teaserText ? `<p>${teaserText}</p>` : ''}
                        </div>
                    </a>
                `;

                container.appendChild(div);
            });
        })
        .catch(err => console.error("Fehler beim Laden verwandter News:", err));
});
