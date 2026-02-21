(function () {
  if (window.location.pathname === "/datenschutz.html") return;

  const placeholderCache = new Map();

  function loadConsentContent(allowExternal = false) {
    function isMobileView() {
      return window.innerWidth <= 768;
    }
    const isMobile = window.innerWidth <= 768;


    document.querySelectorAll(".consent-placeholder").forEach(container => {
      if (!placeholderCache.has(container)) {
        placeholderCache.set(container, container.innerHTML);
      }

      const type = container.dataset.type || "iframe";
      const src = container.dataset.src;
      const height = container.dataset.height || "400";
      const mobileLink = container.dataset.mobileLink || src;


      if (!allowExternal) {
        container.innerHTML = placeholderCache.get(container);
        return;
      }


      if (isMobile && type === "iframe" && mobileLink) {
        container.innerHTML = `
    <div class="rr-mobile-box">
      <p>Die Ergebnisse werden auf Mobilgeräten in einem neuen Tab geöffnet.</p>
      <a href="${mobileLink}" target="_blank" rel="noopener" class="rr-btn">
        Ergebnisse anzeigen
      </a>
    </div>
  `;
        return;
      }

      container.classList.remove("rr-mobile-active");
      container.innerHTML = "";
      const iframe = document.createElement("iframe");
      iframe.src = src;
      iframe.width = "100%";
      iframe.height = height;
      iframe.frameBorder = "0";
      iframe.allowFullscreen = true;
      container.appendChild(iframe);
    });
  }


  function createCookieBanner() {
    if (document.getElementById("cookie-overlay")) return;

    const overlay = document.createElement("div");
    overlay.id = "cookie-overlay";
    overlay.style.cssText = `
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.6);
      backdrop-filter: blur(5px);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 10000;
    `;

    const banner = document.createElement("div");
    banner.id = "cookie-banner";
    banner.style.cssText = `
      background: #fff;
      max-width: 600px;
      width: 90%;
      padding: 2rem;
      border-radius: 12px;
      box-shadow: 0 4px 25px rgba(0,0,0,0.3);
      font-size: 0.95rem;
      line-height: 1.5;
      font-family: 'Poppins', sans-serif;
    `;

    banner.innerHTML = `
      <style>
        #cookie-banner { font-family: 'Poppins', sans-serif; }
        #cookie-banner a { color: #ffc107; text-decoration: underline; }
        .cookie-actions { display:flex; gap:0.75rem; margin-top:1rem; flex-wrap:wrap; justify-content:flex-end; }
        .cookie-actions button { padding:12px 18px; border:none; border-radius:8px; font-weight:600; cursor:pointer; font-family: 'Poppins', sans-serif; }
        #accept-all { background:#ffc107; }
        #decline-all { background:#f0f0f0; }
        #open-preferences { background:#eaeaea; }
        #cookie-detailed { display:none; margin-top:1rem; border-top:1px solid #ddd; padding-top:1rem; }
        .cookie-checkbox { background:#f7f7f7; padding:1rem; border-radius:8px; margin-bottom:1rem; }
        .save-pref { display:none; background:#f0f0f0; padding:10px 16px; border-radius:8px; border:none; cursor:pointer; font-family: 'Poppins', sans-serif; }
      </style>

      <h3>Cookie-Informationen</h3>
      <p>
        Wir verwenden technisch notwendige Cookies für den Betrieb der Website. 
        Externe Inhalte (z.B. YouTube, RaceResult, Instagram/EmbedSocial) werden 
        erst nach Ihrer ausdrücklichen Einwilligung geladen. 
        Dabei können personenbezogene Daten an Drittanbieter übermittelt werden.

        Mehr Infos in der <a href="/datenschutz.html">Datenschutzerklärung</a>.
      </p>

      <div class="cookie-actions">
        <button id="decline-all">Nur notwendige</button>
        <button id="open-preferences">Cookie-Präferenzen</button>
        <button id="accept-all">Alle akzeptieren</button>
      </div>

      <div id="cookie-detailed">
        <div class="cookie-checkbox">
          <input type="checkbox" checked disabled>
          <strong>Notwendige Cookies</strong>
        </div>

        <div class="cookie-checkbox">
          <input type="checkbox" id="chk-external">
          <strong>Externe Inhalte</strong>
          <div style="font-size:0.9rem;">
            YouTube, RaceResult, Instagram / EmbedSocial.
            Beim Laden können personenbezogene Daten übertragen werden. Die Verarbeitung erfolgt auf Grundlage von Art. 6 Abs. 1 lit. a DSGVO (Einwilligung).
          </div>
        </div>

        <div style="text-align:right;">
          <button class="save-pref" id="save-preferences">Präferenzen speichern</button>
        </div>
      </div>
    `;

    overlay.appendChild(banner);
    document.body.appendChild(overlay);


    banner.querySelector("#open-preferences").onclick = () => {
      banner.querySelector("#cookie-detailed").style.display = "block";
      banner.querySelector("#save-preferences").style.display = "block";
      banner.querySelector("#open-preferences").style.display = "none";
    };

    banner.querySelector("#accept-all").onclick = () => {
      localStorage.setItem("cookieChoice", JSON.stringify({ necessary: true, external: true }));
      overlay.remove();
      loadConsentContent(true);
      updateInstagramSection(true);
    };

    banner.querySelector("#decline-all").onclick = () => {
      localStorage.setItem("cookieChoice", JSON.stringify({ necessary: true, external: false }));
      overlay.remove();
      loadConsentContent(false);
      updateInstagramSection(false);
    };

    banner.querySelector("#save-preferences").onclick = () => {
      const external = banner.querySelector("#chk-external").checked;
      localStorage.setItem("cookieChoice", JSON.stringify({ necessary: true, external }));
      overlay.remove();
      loadConsentContent(external);
      updateInstagramSection(external);
    };
  }


  document.addEventListener("click", e => {
    const btn = e.target.closest(".accept-cookies-btn");
    if (!btn) return;

    localStorage.setItem("cookieChoice", JSON.stringify({ necessary: true, external: true }));
    document.getElementById("cookie-overlay")?.remove();
    loadConsentContent(true);
  });


  function getInstagramPlaceholder() {
    return document.getElementById("instagram-placeholder");
  }

  function loadEmbedSocial() {
    if (!document.getElementById("EmbedSocialHashtagScript")) {
      const js = document.createElement("script");
      js.id = "EmbedSocialHashtagScript";
      js.src = "https://embedsocial.com/cdn/ht.js";
      js.async = true;
      document.head.appendChild(js);
    }
  }

  function showInstagramSection() {
    const instagramPlaceholder = getInstagramPlaceholder();
    if (!instagramPlaceholder) return;
    if (instagramPlaceholder.innerHTML) return;

    instagramPlaceholder.innerHTML = `
    <section class="instagram-feed" data-aos="zoom-in-up">
      <h2>Instagram Feed</h2>
      <div class="embedsocial-hashtag" data-ref="84faf809d9dd90588189de7c6e46ff43c4ccf5de">
        <a class="feed-powered-by-es feed-powered-by-es-slider-img es-widget-branding"
           href="https://embedsocial.com/social-media-aggregator/" target="_blank">
          <img src="https://embedsocial.com/cdn/icon/embedsocial-logo.webp" alt="EmbedSocial">
          <div class="es-widget-branding-text">Instagram widget</div>
        </a>
      </div>
    </section>
  `;

    loadEmbedSocial();
    if (window.AOS) AOS.refresh();
  }

  function removeInstagramSection() {
    const instagramPlaceholder = getInstagramPlaceholder();
    if (instagramPlaceholder) instagramPlaceholder.innerHTML = "";
  }

  function updateInstagramSection(allowExternal) {
    if (allowExternal) showInstagramSection();
    else removeInstagramSection();
  }

  function safeReadCookieChoice() {
    const stored = localStorage.getItem("cookieChoice");
    if (!stored) return { external: false, hasChoice: false };

    try {
      const parsed = JSON.parse(stored);
      return { external: !!parsed.external, hasChoice: true };
    } catch (error) {
      return { external: false, hasChoice: false };
    }
  }


  document.addEventListener("DOMContentLoaded", () => {
    const choice = safeReadCookieChoice();
    if (!choice.hasChoice) {
      createCookieBanner();
    } else {
      loadConsentContent(choice.external);
      updateInstagramSection(choice.external);
    }
  });

  let resizeTimeout;
  window.addEventListener("resize", () => {
    clearTimeout(resizeTimeout);
    resizeTimeout = setTimeout(() => {
      const choice = safeReadCookieChoice();
      loadConsentContent(choice.external);
      updateInstagramSection(choice.external);
    }, 150);
  });


  document.addEventListener("click", e => {
    const btn = e.target.closest("#open-cookie-settings");
    if (!btn) return;

    e.preventDefault();
    createCookieBanner();
  });

})();

