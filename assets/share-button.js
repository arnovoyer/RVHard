(function () {
    function triggerFeedback(target) {
        if (!target) {
            return;
        }

        target.classList.remove("is-feedback");
        void target.offsetWidth;
        target.classList.add("is-feedback");

        window.setTimeout(() => {
            target.classList.remove("is-feedback");
        }, 700);
    }

    function setShareLinks(widget) {
        const pageUrl = window.location.href;
        const encodedUrl = encodeURIComponent(pageUrl);
        const title = document.title || "";
        const encodedTitle = encodeURIComponent(title);
        const encodedText = encodeURIComponent(title ? title + " - " + pageUrl : pageUrl);

        const links = widget.querySelectorAll(".share-button__link");
        links.forEach(link => {
            const type = link.getAttribute("data-share");
            if (type === "whatsapp") {
                link.href = "https://wa.me/?text=" + encodedText;
            } else if (type === "facebook") {
                link.href = "https://www.facebook.com/sharer/sharer.php?u=" + encodedUrl;
            } else if (type === "email") {
                link.href = "mailto:?subject=" + encodedTitle + "&body=" + encodedText;
            }

            link.target = "_blank";
            link.rel = "noopener";

            link.addEventListener("click", () => {
                triggerFeedback(link);
            });
        });

        const copyButton = widget.querySelector(".share-button__copy");
        if (copyButton) {
            copyButton.addEventListener("click", () => {
                const icon = copyButton.querySelector("i");
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(pageUrl);
                } else {
                    window.prompt("Link kopieren:", pageUrl);
                }

                if (icon) {
                    icon.classList.remove("fa-link");
                    icon.classList.add("fa-check");
                }

                triggerFeedback(copyButton);

                window.setTimeout(() => {
                    if (icon) {
                        icon.classList.remove("fa-check");
                        icon.classList.add("fa-link");
                    }
                }, 1500);
            });
        }
    }

    window.initShareButtons = function () {
        const widgets = document.querySelectorAll(".share-widget");
        widgets.forEach(widget => setShareLinks(widget));
    };

    if (document.readyState !== "loading") {
        window.initShareButtons();
    } else {
        document.addEventListener("DOMContentLoaded", window.initShareButtons);
    }
})();
