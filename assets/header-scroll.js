let lastScrollY = window.scrollY;
const header = document.querySelector("header");

window.addEventListener("scroll", () => {
  if (window.scrollY <= 0) {
    header.classList.remove("hidden");
  } else if (window.scrollY < lastScrollY) {
    header.classList.remove("hidden");
  } else {
    header.classList.add("hidden");
  }
  lastScrollY = window.scrollY;
});
