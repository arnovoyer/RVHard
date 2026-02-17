function createGridLines() {
    const sponsorenGrid = document.querySelector('.sponsoren-grid');
    if (!sponsorenGrid) return;

    sponsorenGrid.querySelectorAll('.grid-line').forEach(line => line.remove());

    const sponsorLogos = sponsorenGrid.querySelectorAll('.sponsor-logo');

    if (window.innerWidth <= 768) return;

    const gridStyle = getComputedStyle(sponsorenGrid);
    const gap = parseFloat(gridStyle.getPropertyValue('gap'));
    const numColumns = parseInt(gridStyle.getPropertyValue('grid-template-columns').split(' ').length);

    const logoRects = Array.from(sponsorLogos).map(logo => logo.getBoundingClientRect());
    const gridRect = sponsorenGrid.getBoundingClientRect();

    const lineColor = '#666';
    const lineWidth = '1px';
    const borderRadius = '1px';

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
        img.classList.remove('reset', 'slide-in');
    });

    logo.addEventListener('mouseleave', () => {
        img.classList.add('reset');
        setTimeout(() => {
            img.classList.remove('reset');
            img.classList.add('slide-in');
        }, 10);
    });
});