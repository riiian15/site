/**
 * Модуль анимаций — scroll-эффекты, микровзаимодействия, modern motion
 *
 * Что внутри:
 *  • Scroll-анимации через IntersectionObserver (с поддержкой динамического контента)
 *  • Индикатор прогресса скролла
 *  • Smart Header: скрывается при скролле вниз, возвращается при скролле вверх
 *  • Кнопка «Наверх» с плавным появлением
 *  • 3D tilt + light-spotlight для карточек направлений
 *  • Count-up анимация для цифр в hero
 *  • Stagger-анимация для динамических сеток (каталог, корзина)
 *  • Плавное проявление изображений после загрузки
 *  • Уважение prefers-reduced-motion
 */
class Animations {
    constructor() {
        this.reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        this.observer = null;
        this.init();
    }

    init() {
        // Scroll-анимации через Intersection Observer
        this.initScrollAnimations();

        // Индикатор прогресса скролла
        this.initScrollProgress();

        // Pulse для счётчика корзины при изменении
        this.initCartPulse();

        if (this.reducedMotion) return; // дальше — только «живые» эффекты

        // Smart header (скрытие/появление)
        this.initSmartHeader();

        // Кнопка «Наверх»
        this.initBackToTop();

        // 3D tilt + spotlight для карточек
        this.initTiltCards();

        // Count-up для чисел data-count-to
        this.initCountUp();

        // Стаггер для динамических списков + наблюдатель за новыми узлами
        this.initDynamicContent();

        // Плавное появление изображений
        this.initImageFadeIn();
    }

    /* ---------- Scroll-анимации ---------- */
    initScrollAnimations() {
        this.observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    // Отключаем наблюдение после появления
                    this.observer.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        });

        this.observeAll(document);
    }

    /** Навешиваем observer на все анимируемые элементы в заданном контейнере */
    observeAll(root) {
        root.querySelectorAll('.fade-in, .fade-in-left, .fade-in-right, .scale-in, .blur-in')
            .forEach(el => this.observer.observe(el));
    }

    /* ---------- Динамический контент (карточки из API) ---------- */
    initDynamicContent() {
        const staggerSelector = '.product-card, .category-block, .rental-block, .cart-item';

        const applyStagger = (container) => {
            const items = container.querySelectorAll(staggerSelector);
            items.forEach((item, i) => {
                if (item.dataset.staggerReady) return;
                item.dataset.staggerReady = '1';
                item.classList.add('stagger-item');
                item.style.setProperty('--stagger-i', Math.min(i, 12));
                // requestAnimationFrame — чтобы переход точно проигрался
                requestAnimationFrame(() => {
                    requestAnimationFrame(() => item.classList.add('visible'));
                });
            });
        };

        // Применяем к уже отрисованным сеткам
        document.querySelectorAll('.products-grid, .catalog-layout, .cart-layout, section').forEach(applyStagger);

        // И следим за новыми узлами (рендер после fetch)
        this.mutationObserver = new MutationObserver((mutations) => {
            mutations.forEach(m => {
                m.addedNodes.forEach(node => {
                    if (node.nodeType !== 1) return;
                    if (node.matches && node.matches(staggerSelector)) {
                        applyStagger(node.parentElement);
                    } else if (node.querySelectorAll) {
                        applyStagger(node);
                    }
                });
            });
        });

        this.mutationObserver.observe(document.body, { childList: true, subtree: true });
    }

    /* ---------- Проявление картинок ---------- */
    initImageFadeIn() {
        const markLoaded = (img) => {
            if (img.complete && img.naturalWidth > 0) {
                img.classList.add('loaded');
            } else {
                img.addEventListener('load', () => img.classList.add('loaded'), { once: true });
                img.addEventListener('error', () => img.classList.add('loaded'), { once: true });
            }
        };

        document.querySelectorAll('img').forEach(markLoaded);

        // Новые картинки тоже подхватываем
        const obs = new MutationObserver((mutations) => {
            mutations.forEach(m => m.addedNodes.forEach(node => {
                if (node.nodeType !== 1) return;
                if (node.tagName === 'IMG') markLoaded(node);
                if (node.querySelectorAll) node.querySelectorAll('img').forEach(markLoaded);
            }));
        });
        obs.observe(document.body, { childList: true, subtree: true });
    }

    /* ---------- Smart Header ---------- */
    initSmartHeader() {
        const header = document.getElementById('header');
        if (!header) return;

        let lastY = window.scrollY;
        let ticking = false;

        const update = () => {
            const y = window.scrollY;
            header.classList.toggle('scrolled', y > 24);

            // Не прячем, когда открыто мобильное меню или фокус в шапке
            const menuOpen = header.querySelector('.nav.active');
            if (!menuOpen && !header.contains(document.activeElement)) {
                if (y > lastY && y > 260) {
                    header.classList.add('hidden-up');
                } else {
                    header.classList.remove('hidden-up');
                }
            }

            lastY = y;
            ticking = false;
        };

        window.addEventListener('scroll', () => {
            if (!ticking) {
                requestAnimationFrame(update);
                ticking = true;
            }
        }, { passive: true });
    }

    /* ---------- Кнопка «Наверх» ---------- */
    initBackToTop() {
        const btn = document.createElement('button');
        btn.className = 'back-to-top';
        btn.setAttribute('aria-label', 'Наверх');
        btn.innerHTML = '&#8593;';
        document.body.appendChild(btn);

        let ticking = false;
        window.addEventListener('scroll', () => {
            if (ticking) return;
            ticking = true;
            requestAnimationFrame(() => {
                btn.classList.toggle('visible', window.scrollY > 600);
                ticking = false;
            });
        }, { passive: true });

        btn.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    /* ---------- 3D Tilt + Spotlight ---------- */
    initTiltCards() {
        const isFinePointer = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
        if (!isFinePointer) return;

        const MAX_TILT = 6; // градусы

        const attach = (card) => {
            if (card.dataset.tiltReady) return;
            card.dataset.tiltReady = '1';

            card.addEventListener('mousemove', (e) => {
                const rect = card.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;

                // Световое пятно за курсором
                card.style.setProperty('--mx', `${x}px`);
                card.style.setProperty('--my', `${y}px`);

                // Лёгкий наклон к курсору
                const rx = ((y / rect.height) - 0.5) * -MAX_TILT * 2;
                const ry = ((x / rect.width) - 0.5) * MAX_TILT * 2;
                card.style.transform =
                    `perspective(800px) rotateX(${rx.toFixed(2)}deg) rotateY(${ry.toFixed(2)}deg) translateY(-8px)`;
            });

            card.addEventListener('mouseleave', () => {
                card.style.transform = '';
            });
        };

        const bindAll = (root) => root.querySelectorAll('.direction-card').forEach(attach);
        bindAll(document);

        const obs = new MutationObserver(() => bindAll(document));
        obs.observe(document.body, { childList: true, subtree: true });
    }

    /* ---------- Count-up для чисел ---------- */
    initCountUp() {
        const els = document.querySelectorAll('[data-count-to]');
        if (!els.length) return;

        const animate = (el) => {
            const target = parseFloat(el.dataset.countTo);
            const duration = 1600;
            const suffix = el.dataset.countSuffix || '';
            const start = performance.now();

            const step = (now) => {
                const p = Math.min((now - start) / duration, 1);
                const eased = 1 - Math.pow(1 - p, 4); // easeOutQuart
                const value = Math.round(target * eased);
                el.textContent = value.toLocaleString('ru-RU') + suffix;
                if (p < 1) requestAnimationFrame(step);
            };
            requestAnimationFrame(step);
        };

        const io = new IntersectionObserver((entries) => {
            entries.forEach(e => {
                if (e.isIntersecting) {
                    animate(e.target);
                    io.unobserve(e.target);
                }
            });
        }, { threshold: 0.5 });

        els.forEach(el => io.observe(el));
    }

    /* ---------- Прогресс скролла ---------- */
    initScrollProgress() {
        // Создаём индикатор прогресса
        const progressBar = document.createElement('div');
        progressBar.className = 'scroll-progress';
        progressBar.style.width = '0%';
        document.body.appendChild(progressBar);

        let ticking = false;
        window.addEventListener('scroll', () => {
            if (ticking) return;
            ticking = true;
            requestAnimationFrame(() => {
                const scrollTop = window.scrollY;
                const docHeight = document.documentElement.scrollHeight - window.innerHeight;
                const progress = docHeight > 0 ? (scrollTop / docHeight) * 100 : 0;
                progressBar.style.width = `${progress}%`;
                ticking = false;
            });
        }, { passive: true });
    }

    /* ---------- Pulse счётчика корзины ---------- */
    initCartPulse() {
        // Следим за изменениями счётчика корзины
        const cartCount = document.getElementById('cartCount');
        if (!cartCount) return;

        let lastCount = parseInt(cartCount.textContent) || 0;

        // Используем MutationObserver для отслеживания изменений
        const observer = new MutationObserver((mutations) => {
            mutations.forEach(mutation => {
                if (mutation.type === 'childList' || mutation.type === 'characterData') {
                    const newCount = parseInt(cartCount.textContent) || 0;
                    if (newCount !== lastCount) {
                        cartCount.classList.remove('pulse');
                        void cartCount.offsetWidth; // Reset animation
                        cartCount.classList.add('pulse');
                        lastCount = newCount;
                    }
                }
            });
        });

        observer.observe(cartCount, {
            childList: true,
            characterData: true,
            subtree: true
        });
    }
}

// Инициализация
document.addEventListener('DOMContentLoaded', () => {
    window.animations = new Animations();
});
