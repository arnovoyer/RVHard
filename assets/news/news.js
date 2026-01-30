// assets/script-news.js

document.addEventListener('DOMContentLoaded', () => {
    const galleries = document.querySelectorAll('.news-detail-gallery-wrapper');

    galleries.forEach(wrapper => {
        const gallery = wrapper.querySelector('.news-detail-gallery');
        const slides = gallery.querySelectorAll('.gallery-slide');
        const prevButton = wrapper.querySelector('.gallery-control.prev');
        const nextButton = wrapper.querySelector('.gallery-control.next');

        if (slides.length === 0) {
            console.warn("No slides found in gallery:", gallery.id);
            return;
        }

        let currentSlide = 0;

        function showSlide(index) {
            slides.forEach(slide => {
                slide.style.display = 'none';
            });
            slides[index].style.display = 'block';
        }

        function nextSlide() {
            currentSlide = (currentSlide + 1) % slides.length;
            showSlide(currentSlide);
        }

        function prevSlide() {
            currentSlide = (currentSlide - 1 + slides.length) % slides.length;
            showSlide(currentSlide);
        }

        if (prevButton) prevButton.addEventListener('click', prevSlide);
        if (nextButton) nextButton.addEventListener('click', nextSlide);

        showSlide(currentSlide);

        /* ------------------------------------
         * 🖼️ Lightbox Funktionalität
         * ------------------------------------ */
        const lightbox = document.createElement('div');
        lightbox.classList.add('lightbox');
        lightbox.innerHTML = `
            <button class="close-btn" aria-label="Schließen">&times;</button>
            <button class="prev-btn" aria-label="Vorheriges Bild">&#10094;</button>
            <button class="next-btn" aria-label="Nächstes Bild">&#10095;</button>
            <img src="" alt="Vergrößertes Bild">
        `;
        document.body.appendChild(lightbox);

        const lightboxImg = lightbox.querySelector('img');
        const closeBtn = lightbox.querySelector('.close-btn');
        const prevLightbox = lightbox.querySelector('.prev-btn');
        const nextLightbox = lightbox.querySelector('.next-btn');

        slides.forEach((slide, index) => {
            slide.addEventListener('click', () => {
                currentSlide = index;
                openLightbox();
            });
        });

        function openLightbox() {
            const bg = slides[currentSlide].style.backgroundImage;
            const imgUrl = bg.slice(5, -2); // entfernt url("...") Syntax
            lightboxImg.src = imgUrl;
            lightbox.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeLightbox() {
            lightbox.style.display = 'none';
            document.body.style.overflow = '';
            lightbox.classList.remove('zoomed');
        }

        function navigateLightbox(direction) {
            currentSlide = (currentSlide + direction + slides.length) % slides.length;
            openLightbox();
        }

        closeBtn.addEventListener('click', closeLightbox);
        prevLightbox.addEventListener('click', () => navigateLightbox(-1));
        nextLightbox.addEventListener('click', () => navigateLightbox(1));

        // Zoom durch Klick auf das Bild
        lightboxImg.addEventListener('click', () => {
            lightbox.classList.toggle('zoomed');
        });

        // Klick auf Hintergrund = schließen
        lightbox.addEventListener('click', e => {
            if (e.target === lightbox) closeLightbox();
        });

        // Tastatursteuerung
        document.addEventListener('keydown', e => {
            if (lightbox.style.display === 'flex') {
                if (e.key === 'ArrowRight') navigateLightbox(1);
                if (e.key === 'ArrowLeft') navigateLightbox(-1);
                if (e.key === 'Escape') closeLightbox();
            }
        });
    });
});


// ---------------------------------------------------
// 📰 Related News Laden
// ---------------------------------------------------
document.addEventListener("DOMContentLoaded", () => {
    const currentSlug = "Rennbericht-CadFish-Lübeck-Juni-2024"; // ← pro Seite anpassen
    fetch("/assets/news/news.json")
        .then(res => res.json())
        .then(news => {
            const relatedContainer = document.querySelector(".related-news-list");
            if (!relatedContainer) return;

            const otherNews = news.filter(item => item.slug !== currentSlug);
            otherNews.sort(() => 0.5 - Math.random());
            otherNews.slice(0, 3).forEach(item => {
                const div = document.createElement("div");
                div.className = "related-news-item";
                div.innerHTML = `
                    <a href="/news/${item.slug}.html">
                        <img src="${item.image}" alt="${item.title}">
                        <h3>${item.title}</h3>
                        <p>${item.content.substring(0, 100)}...</p>
                    </a>
                `;
                relatedContainer.appendChild(div);
            });
        })
        .catch(err => {
            console.error("Fehler beim Laden der News:", err);
        });
});
