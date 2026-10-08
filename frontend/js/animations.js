/**
 * Модуль анимаций — scroll-эффекты, плавные появления
 */
class Animations {
    constructor() {
        this.init();
    }

    init() {
        // Scroll-анимации через Intersection Observer
        this.initScrollAnimations();
        
        // Индикатор прогресса скролла
        this.initScrollProgress();
        
        // Pulse для счётчика корзины при изменении
        this.initCartPulse();
    }

    initScrollAnimations() {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    // Отключаем наблюдение после появления
                    observer.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        });

        // Наблюдаем за всеми анимируемыми элементами
        document.querySelectorAll('.fade-in, .fade-in-left, .fade-in-right, .scale-in').forEach(el => {
            observer.observe(el);
        });
    }

    initScrollProgress() {
        // Создаём индикатор прогресса
        const progressBar = document.createElement('div');
        progressBar.className = 'scroll-progress';
        progressBar.style.width = '0%';
        document.body.appendChild(progressBar);

        window.addEventListener('scroll', () => {
            const scrollTop = window.scrollY;
            const docHeight = document.documentElement.scrollHeight - window.innerHeight;
            const progress = (scrollTop / docHeight) * 100;
            progressBar.style.width = `${progress}%`;
        });
    }

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
    new Animations();
});