/**
 * Góc Nhỏ — Animations Engine
 * Scroll-reveal, parallax, hero particles, image lightbox, back-to-top, tilt
 * Không sửa đổi chức năng gốc — chỉ bổ sung lớp hiệu ứng thị giác.
 */
(function () {
  'use strict';

  /* ============================================================
     1. SCROLL-REVEAL: Hiện dần khi phần tử xuất hiện trong viewport
     ============================================================ */
  function initScrollReveal() {
    // Gắn class reveal cho các phần tử cần animate
    const selectors = [
      { sel: '.featured-card',            cls: 'reveal-scale' },
      { sel: '.section-heading',          cls: 'reveal' },
      { sel: '.feed-intro',              cls: 'reveal' },
      { sel: '.composer-card',           cls: 'reveal' },
      { sel: '.about-card',             cls: 'reveal-right' },
    ];

    selectors.forEach(({ sel, cls }) => {
      document.querySelectorAll(sel).forEach((el, idx) => {
        if (!el.classList.contains('reveal') &&
            !el.classList.contains('reveal-left') &&
            !el.classList.contains('reveal-right') &&
            !el.classList.contains('reveal-scale')) {
          el.classList.add(cls);
        }
        if (idx < 6) el.classList.add('stagger-' + (idx + 1));
      });
    });

    const allReveals = document.querySelectorAll('.reveal, .reveal-left, .reveal-right, .reveal-scale');
    if (!allReveals.length) return;

    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('revealed');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

    allReveals.forEach(el => observer.observe(el));
  }

  /* ============================================================
     2. HERO PARTICLES: Tạo hạt lấp lánh trôi nổi trên hero
     ============================================================ */
  function initHeroParticles() {
    const hero = document.querySelector('.blog-hero');
    if (!hero) return;

    // Tạo container
    let container = hero.querySelector('.hero-particles');
    if (!container) {
      container = document.createElement('div');
      container.className = 'hero-particles';
      container.setAttribute('aria-hidden', 'true');
      hero.insertBefore(container, hero.firstChild);
    }

    const count = 18;
    const frag = document.createDocumentFragment();
    for (let i = 0; i < count; i++) {
      const p = document.createElement('span');
      p.className = 'hero-particle';
      const size = 2 + Math.random() * 4;
      p.style.cssText = `
        width:${size}px; height:${size}px;
        left:${Math.random() * 100}%;
        bottom:${-10 - Math.random() * 20}%;
        animation-duration:${8 + Math.random() * 14}s;
        animation-delay:${Math.random() * 10}s;
        opacity:${0.2 + Math.random() * 0.5};
      `;
      frag.appendChild(p);
    }
    container.appendChild(frag);
  }

  /* ============================================================
     3. PARALLAX: Hero background dịch chuyển theo scroll
     ============================================================ */
  function initParallax() {
    const hero = document.querySelector('.blog-hero');
    if (!hero) return;

    let ticking = false;
    function onScroll() {
      if (ticking) return;
      ticking = true;
      requestAnimationFrame(() => {
        const rect = hero.getBoundingClientRect();
        if (rect.bottom > 0) {
          const offset = window.scrollY * 0.25;
          hero.style.backgroundPositionY = `calc(52% + ${offset}px)`;
        }
        ticking = false;
      });
    }
    window.addEventListener('scroll', onScroll, { passive: true });
  }

  /* ============================================================
     4. IMAGE TILT: 3D tilt nhẹ khi rê chuột lên featured cards
     ============================================================ */
  function initCardTilt() {
    const cards = document.querySelectorAll('.featured-card');
    cards.forEach(card => {
      card.addEventListener('mousemove', (e) => {
        const rect = card.getBoundingClientRect();
        const x = (e.clientX - rect.left) / rect.width - 0.5;
        const y = (e.clientY - rect.top) / rect.height - 0.5;
        card.style.transform = `perspective(800px) rotateY(${x * 8}deg) rotateX(${-y * 6}deg) translateY(-4px)`;
      });

      card.addEventListener('mouseleave', () => {
        card.style.transform = '';
        card.style.transition = 'transform 0.5s cubic-bezier(0.22, 1, 0.36, 1)';
        setTimeout(() => { card.style.transition = ''; }, 500);
      });
    });
  }

  /* ============================================================
     5. POST IMAGE EFFECTS: Bọc ảnh, thêm shine, lightbox preview
     ============================================================ */
  function wrapPostImages() {
    // Bọc ảnh thumbnail trong feed vào wrapper để có shine effect
    document.querySelectorAll('.post-image, .post-thumbnail').forEach(img => {
      if (img.parentElement.classList.contains('post-image-wrapper')) return;
      const wrapper = document.createElement('div');
      wrapper.className = 'post-image-wrapper';
      img.parentElement.insertBefore(wrapper, img);
      wrapper.appendChild(img);

      // Badge ảnh
      const badge = document.createElement('span');
      badge.className = 'post-image-badge';
      badge.innerHTML = '<i class="fa-regular fa-image"></i> Xem ảnh';
      wrapper.appendChild(badge);
    });
  }

  // Lightbox đơn giản khi click ảnh trong post
  function initImageLightbox() {
    // Tạo overlay lightbox 1 lần
    const overlay = document.createElement('div');
    overlay.className = 'lightbox-overlay';
    overlay.innerHTML = `
      <div class="lightbox-content">
        <button class="lightbox-close" aria-label="Đóng">&times;</button>
        <img class="lightbox-img" src="" alt="Xem ảnh lớn">
      </div>
    `;
    document.body.appendChild(overlay);

    const lbImg = overlay.querySelector('.lightbox-img');
    const lbClose = overlay.querySelector('.lightbox-close');

    function closeLightbox() {
      overlay.classList.remove('active');
      setTimeout(() => { lbImg.src = ''; }, 300);
    }

    lbClose.addEventListener('click', closeLightbox);
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) closeLightbox();
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') closeLightbox();
    });

    // Delegate click trên ảnh bài viết
    document.addEventListener('click', (e) => {
      const img = e.target.closest('.post-image-wrapper img.post-image, .post-image-wrapper img.post-thumbnail');
      if (!img) return;
      e.stopPropagation();
      e.preventDefault();
      lbImg.src = img.src;
      overlay.classList.add('active');
    });
  }

  /* ============================================================
     6. BACK-TO-TOP BUTTON
     ============================================================ */
  function initBackToTop() {
    const btn = document.createElement('button');
    btn.className = 'back-to-top';
    btn.setAttribute('aria-label', 'Lên đầu trang');
    btn.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m18 15-6-6-6 6"/></svg>';
    document.body.appendChild(btn);

    btn.addEventListener('click', () => {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    let ticking = false;
    window.addEventListener('scroll', () => {
      if (ticking) return;
      ticking = true;
      requestAnimationFrame(() => {
        btn.classList.toggle('visible', window.scrollY > 400);
        ticking = false;
      });
    }, { passive: true });
  }

  /* ============================================================
     7. NAVBAR SHRINK: Thu nhỏ header khi cuộn xuống
     ============================================================ */
  function initNavbarShrink() {
    const header = document.querySelector('.blog-header');
    if (!header) return;

    let ticking = false;
    window.addEventListener('scroll', () => {
      if (ticking) return;
      ticking = true;
      requestAnimationFrame(() => {
        header.classList.toggle('header-scrolled', window.scrollY > 60);
        ticking = false;
      });
    }, { passive: true });
  }

  /* ============================================================
     8. FEED MUTATION OBSERVER: Áp dụng hiệu ứng cho bài viết mới
     ============================================================ */
  function initFeedObserver() {
    const container = document.getElementById('feed-container');
    if (!container) return;

    const mo = new MutationObserver(() => {
      wrapPostImages();
      // Observe mới thêm post-card cho scroll-reveal
      container.querySelectorAll('.post-card:not(.anim-ready)').forEach((card, idx) => {
        card.classList.add('anim-ready');
        card.style.animationDelay = `${0.05 + idx * 0.07}s`;
      });
    });

    mo.observe(container, { childList: true, subtree: true });
  }

  /* ============================================================
     9. HEADER ACTIVE LINK ON SCROLL
     ============================================================ */
  function initActiveNav() {
    const sections = ['home', 'feed', 'about'];
    const links = document.querySelectorAll('.blog-nav-link');
    if (!links.length) return;

    let ticking = false;
    window.addEventListener('scroll', () => {
      if (ticking) return;
      ticking = true;
      requestAnimationFrame(() => {
        let current = '';
        sections.forEach(id => {
          const el = document.getElementById(id);
          if (el && el.getBoundingClientRect().top <= 150) current = id;
        });
        links.forEach(link => {
          const href = link.getAttribute('href') || '';
          const target = href.replace('#', '');
          link.classList.toggle('is-active', target === current);
        });
        ticking = false;
      });
    }, { passive: true });
  }

  /* ============================================================
     KHỞI CHẠY TẤT CẢ
     ============================================================ */
  function init() {
    // Respect prefers-reduced-motion
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      initBackToTop(); // vẫn giữ nút cuộn lên đầu trang
      return;
    }

    initScrollReveal();
    initHeroParticles();
    initParallax();
    initCardTilt();
    wrapPostImages();
    initImageLightbox();
    initBackToTop();
    initNavbarShrink();
    initFeedObserver();
    initActiveNav();
  }

  // Chờ DOM sẵn sàng
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
