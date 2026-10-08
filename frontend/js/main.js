/**
 * Основной скрипт — инициализация, меню, шапка
 */
document.addEventListener('DOMContentLoaded', async () => {

    // ===== БУРГЕР-МЕНЮ =====
    const burger = document.getElementById('burger');
    const nav = document.getElementById('nav');

    if (burger && nav) {
        burger.addEventListener('click', () => {
            burger.classList.toggle('active');
            nav.classList.toggle('active');
        });

        // Закрытие при клике на ссылку
        nav.querySelectorAll('.nav__link').forEach(link => {
            link.addEventListener('click', () => {
                burger.classList.remove('active');
                nav.classList.remove('active');
            });
        });

        // Закрытие при клике вне меню
        document.addEventListener('click', (e) => {
            if (!nav.contains(e.target) && !burger.contains(e.target)) {
                burger.classList.remove('active');
                nav.classList.remove('active');
            }
        });
    }

    // ===== ШАПКА: тень при скролле =====
    const header = document.getElementById('header');
    if (header) {
        window.addEventListener('scroll', () => {
            if (window.scrollY > 10) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });
    }

    // ===== ИНИЦИАЛИЗАЦИЯ ФОРМЫ =====
    FormHandler.initMainForm();

    // ===== ОБНОВЛЕНИЕ СЧЁТЧИКА КОРЗИНЫ =====
    cart.updateCount();

    // ===== ЗАГРУЗКА ДАННЫХ (кэширование) =====
    try {
        await api.loadData();
        console.log('Данные загружены успешно');
    } catch (err) {
        console.error('Ошибка загрузки данных:', err);
    }
});