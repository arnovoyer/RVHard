document.addEventListener('DOMContentLoaded', () => {
    const galleries = document.querySelectorAll('.news-detail-gallery');

    galleries.forEach(gallery => {
        const images = gallery.querySelectorAll('.gallery-image');
        const prevButton = gallery.querySelector('.prev');
        const nextButton = gallery.querySelector('.next');
        let currentIndex = 0;

        if (images.length > 0) {
            if (prevButton) {
                prevButton.addEventListener('click', () => {
                    images[currentIndex].style.display = 'none';
                    currentIndex = (currentIndex - 1 + images.length) % images.length;
                    images[currentIndex].style.display = 'block';
                });
            }

            if (nextButton) {
                nextButton.addEventListener('click', () => {
                    images[currentIndex].style.display = 'none';
                    currentIndex = (currentIndex + 1) % images.length;
                    images[currentIndex].style.display = 'block';
                });
            }

            // Hover-Effekt für die Pfeile (optional, kann auch rein über CSS gesteuert werden)
            gallery.addEventListener('mouseenter', () => {
                if (prevButton) prevButton.style.opacity = '1';
                if (nextButton) nextButton.style.opacity = '1';
            });

            gallery.addEventListener('mouseleave', () => {
                if (prevButton) prevButton.style.opacity = '0.5'; // Oder ganz ausblenden
                if (nextButton) nextButton.style.opacity = '0.5'; // Oder ganz ausblenden
            });

            // Initialer Zustand der Pfeile (optional)
            if (images.length > 1) {
                if (prevButton) prevButton.style.opacity = '0.5';
                if (nextButton) nextButton.style.opacity = '0.5';
            } else {
                // Bei nur einem Bild Pfeile ausblenden
                if (prevButton) prevButton.style.display = 'none';
                if (nextButton) nextButton.style.display = 'none';
            }
        } else {
            // Keine Bilder in der Galerie
            if (prevButton) prevButton.style.display = 'none';
            if (nextButton) nextButton.style.display = 'none';
        }
    });
});