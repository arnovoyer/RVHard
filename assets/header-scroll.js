let lastScrollY = window.scrollY;
const header = document.querySelector("header");

window.addEventListener("scroll", () => {
  if (window.scrollY <= 0) {
    // Ganz oben → Navigation sichtbar
    header.classList.remove("hidden");
  } else if (window.scrollY < lastScrollY) {
    // Nach oben scrollen → Navigation sichtbar
    header.classList.remove("hidden");
  } else {
    // Nach unten scrollen → Navigation verstecken
    header.classList.add("hidden");
  }
  lastScrollY = window.scrollY;
});
