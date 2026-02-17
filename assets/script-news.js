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

            gallery.addEventListener('mouseenter', () => {
                if (prevButton) prevButton.style.opacity = '1';
                if (nextButton) nextButton.style.opacity = '1';
            });

            gallery.addEventListener('mouseleave', () => {
                if (prevButton) prevButton.style.opacity = '0.5';
                if (nextButton) nextButton.style.opacity = '0.5';
            });


            if (images.length > 1) {
                if (prevButton) prevButton.style.opacity = '0.5';
                if (nextButton) nextButton.style.opacity = '0.5';
            } else {
                if (prevButton) prevButton.style.display = 'none';
                if (nextButton) nextButton.style.display = 'none';
            }
        } else {
            if (prevButton) prevButton.style.display = 'none';
            if (nextButton) nextButton.style.display = 'none';
        }
    });
});