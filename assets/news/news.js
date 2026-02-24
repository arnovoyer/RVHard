function initNewsGalleries(root = document) {
    const galleries = root.querySelectorAll('.news-detail-gallery-wrapper');

    galleries.forEach(wrapper => {
        if (wrapper.dataset.galleryInitialized === '1') return;
        wrapper.dataset.galleryInitialized = '1';

        const gallery = wrapper.querySelector('.news-detail-gallery');
        if (!gallery) return;
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
            const imgUrl = bg.slice(5, -2);
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

        lightboxImg.addEventListener('click', () => {
            lightbox.classList.toggle('zoomed');
        });

        lightbox.addEventListener('click', e => {
            if (e.target === lightbox) closeLightbox();
        });

        document.addEventListener('keydown', e => {
            if (lightbox.style.display === 'flex') {
                if (e.key === 'ArrowRight') navigateLightbox(1);
                if (e.key === 'ArrowLeft') navigateLightbox(-1);
                if (e.key === 'Escape') closeLightbox();
            }
        });
    });
}

document.addEventListener("DOMContentLoaded", () => {
    initNewsGalleries();
    // Related News werden ausschließlich über related-news-loader.js gerendert.
});

window.initNewsGalleries = initNewsGalleries;
