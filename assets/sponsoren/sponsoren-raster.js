function createGridLines() {
    const sponsorenGrid = document.querySelector('.sponsoren-grid');
    if (!sponsorenGrid) return;

    // Entferne vorherige Rasterlinien
    sponsorenGrid.querySelectorAll('.grid-line').forEach(line => line.remove());

    const sponsorLogos = sponsorenGrid.querySelectorAll('.sponsor-logo');

    // Mobile Check
    if (window.innerWidth <= 768) return; // Auf Mobile keine Linien erstellen

    const gridStyle = getComputedStyle(sponsorenGrid);
    const gap = parseFloat(gridStyle.getPropertyValue('gap'));
    const numColumns = parseInt(gridStyle.getPropertyValue('grid-template-columns').split(' ').length);

    const logoRects = Array.from(sponsorLogos).map(logo => logo.getBoundingClientRect());
    const gridRect = sponsorenGrid.getBoundingClientRect();

    const lineColor = '#666'; // Dunkleres Grau
    const lineWidth = '1px';
    const borderRadius = '1px';

    // Horizontale Linien
    for (let i = numColumns; i < logoRects.length; i++) {
        const line = document.createElement('div');
        line.classList.add('grid-line', 'grid-line-horizontal');
        line.style.top = (logoRects[i].top - gap / 2 - gridRect.top) + 'px';
        line.style.left = (logoRects[i % numColumns].left - gridRect.left) + 'px';
        line.style.width = (logoRects[i % numColumns].width) + 'px';
        line.style.backgroundColor = lineColor;
        line.style.borderRadius = borderRadius;
        line.style.height = lineWidth;
        line.style.position = 'absolute';
        sponsorenGrid.appendChild(line);
    }

    // Vertikale Linien
    for (let i = 0; i < sponsorLogos.length; i++) {
        if ((i + 1) % numColumns !== 0) {
            const line = document.createElement('div');
            line.classList.add('grid-line', 'grid-line-vertical');
            line.style.left = (logoRects[i].right + gap / 2 - gridRect.left) + 'px';
            line.style.top = (logoRects[i].top - gridRect.top) + 'px';
            line.style.height = (logoRects[i].height) + 'px';
            line.style.backgroundColor = lineColor;
            line.style.borderRadius = borderRadius;
            line.style.width = lineWidth;
            line.style.position = 'absolute';
            sponsorenGrid.appendChild(line);
        }
    }
}

window.addEventListener('load', createGridLines);
window.addEventListener('resize', createGridLines);
const sponsorLogos = document.querySelectorAll('.sponsor-logo');

sponsorLogos.forEach(logo => {
    const img = logo.querySelector('img');

    logo.addEventListener('mouseenter', () => {
        img.classList.remove('reset', 'slide-in'); // Entferne Reset- und Slide-In-Klassen beim Hover
    });

    logo.addEventListener('mouseleave', () => {
        img.classList.add('reset'); // Setze die Reset-Klasse, um das Logo nach oben zu positionieren
        setTimeout(() => {
            img.classList.remove('reset'); // Entferne die Reset-Klasse nach kurzer Zeit
            img.classList.add('slide-in'); // Füge die Slide-In-Klasse hinzu, um die Animation zu starten
        }, 10); // Eine kleine Verzögerung kann helfen, den Übergang sauberer zu gestalten
    });
});