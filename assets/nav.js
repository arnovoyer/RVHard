document.addEventListener('DOMContentLoaded', () => {
    const toggleBtn = document.querySelector('.menu-toggle');
    const navMenu = document.querySelector('.nav-main-menu');
    const dropdowns = document.querySelectorAll('.dropdown');
    const subDropdowns = document.querySelectorAll('.sub-dropdown');

    // Menü-Button toggelt Hauptmenü (nur auf Mobile sichtbar)
    if (toggleBtn && navMenu) {
        toggleBtn.addEventListener('click', () => {
            navMenu.classList.toggle('active');
            document.body.classList.toggle('no-scroll', navMenu.classList.contains('active'));
        });
    }

    function isMobile() {
        return window.innerWidth <= 794;
    }

    // Haupt-Dropdowns
    dropdowns.forEach(dropdown => {
        const link = dropdown.querySelector('a');

        link.addEventListener('click', (e) => {
            if (isMobile()) {
                e.preventDefault();
                e.stopPropagation();

                const isActive = dropdown.classList.contains('active-dropdown');

                // Alle anderen schließen
                dropdowns.forEach(d => d.classList.remove('active-dropdown'));
                subDropdowns.forEach(s => s.classList.remove('active-sub-dropdown'));

                // Nur das aktuelle öffnen, falls es vorher nicht aktiv war
                if (!isActive) {
                    dropdown.classList.add('active-dropdown');
                }
            }
        });
    });

    // Sub-Dropdowns
    subDropdowns.forEach(sub => {
        const link = sub.querySelector('a');

        link.addEventListener('click', (e) => {
            if (isMobile()) {
                e.preventDefault();
                e.stopPropagation();

                const isActive = sub.classList.contains('active-sub-dropdown');

                // Alle anderen Sub-Dropdowns schließen
                subDropdowns.forEach(s => s.classList.remove('active-sub-dropdown'));

                // Haupt-Dropdowns offen lassen (nicht schließen, sonst klappt das ganze Menü zu)

                // Nur das aktuelle öffnen, falls es vorher nicht aktiv war
                if (!isActive) {
                    sub.classList.add('active-sub-dropdown');
                }
            }
        });
    });

    // Klick außerhalb schließt alles, aber nicht wenn innerhalb geklickt wird
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

