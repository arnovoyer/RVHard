document.addEventListener('DOMContentLoaded', () => {
    fetch('/assets/navigationneu.html')
        .then(response => response.text())
        .then(html => {
            const navContainer = document.getElementById('navigation-container');
            if (navContainer) {
                navContainer.innerHTML = html;
                initNavigationMenu();
            }
        })
        .catch(error => {
            console.error("Fehler beim Laden der Navigation:", error);
        });
});

function initNavigationMenu() {
    const toggleBtn = document.querySelector('.menu-toggle');
    const navMenu = document.querySelector('.nav-main-menu');
    const dropdowns = document.querySelectorAll('.dropdown');
    const subDropdowns = document.querySelectorAll('.sub-dropdown');

    if (toggleBtn && navMenu) {
        toggleBtn.addEventListener('click', () => {
            navMenu.classList.toggle('active');
            toggleBtn.classList.toggle('rotated');
        });
    }

    dropdowns.forEach(drop => {
        drop.addEventListener('click', (e) => {
            e.stopPropagation();
            drop.classList.toggle('open');
            dropdowns.forEach(d => { if (d !== drop) d.classList.remove('open'); });
        });
    });

    subDropdowns.forEach(sub => {
        sub.addEventListener('click', (e) => {
            e.stopPropagation();
            sub.classList.toggle('open');
            subDropdowns.forEach(s => { if (s !== sub) s.classList.remove('open'); });
        });
    });

    document.body.addEventListener('click', () => {
        dropdowns.forEach(drop => drop.classList.remove('open'));
        subDropdowns.forEach(sub => sub.classList.remove('open'));
    });
}
