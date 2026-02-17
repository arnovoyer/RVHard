document.addEventListener('DOMContentLoaded', () => {
    const toggleBtn = document.querySelector('.menu-toggle');
    const navMenu = document.querySelector('.nav-main-menu');
    const dropdowns = document.querySelectorAll('.dropdown');
    const subDropdowns = document.querySelectorAll('.sub-dropdown');

    if (toggleBtn && navMenu) {
        toggleBtn.addEventListener('click', () => {
            navMenu.classList.toggle('active');
            document.body.classList.toggle('no-scroll', navMenu.classList.contains('active'));
        });
    }

    function isMobile() {
        return window.innerWidth <= 794;
    }

    dropdowns.forEach(dropdown => {
        const link = dropdown.querySelector('a');

        link.addEventListener('click', (e) => {
            if (isMobile()) {
                e.preventDefault();
                e.stopPropagation();

                const isActive = dropdown.classList.contains('active-dropdown');

                dropdowns.forEach(d => d.classList.remove('active-dropdown'));
                subDropdowns.forEach(s => s.classList.remove('active-sub-dropdown'));

                if (!isActive) {
                    dropdown.classList.add('active-dropdown');
                }
            }
        });
    });

    subDropdowns.forEach(sub => {
        const link = sub.querySelector('a');

        link.addEventListener('click', (e) => {
            if (isMobile()) {
                e.preventDefault();
                e.stopPropagation();

                const isActive = sub.classList.contains('active-sub-dropdown');

                subDropdowns.forEach(s => s.classList.remove('active-sub-dropdown'));

                if (!isActive) {
                    sub.classList.add('active-sub-dropdown');
                }
            }
        });
    });

    document.addEventListener('click', (e) => {
        if (isMobile()) {
            const clickedInsideNav = e.target.closest('.nav');
            if (!clickedInsideNav) {
                dropdowns.forEach(d => d.classList.remove('active-dropdown'));
                subDropdowns.forEach(s => s.classList.remove('active-sub-dropdown'));
            }
        }
    });
});
toggleBtn.addEventListener('click', () => {
    navMenu.classList.toggle('active');
    toggleBtn.classList.toggle('rotated');
});

