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
    if (typeof FormHandler !== 'undefined') FormHandler.initMainForm();

    // ===== ОБНОВЛЕНИЕ СЧЁТЧИКА КОРЗИНЫ =====
    if (typeof cart !== 'undefined') cart.updateCount();

    // ===== ЗАГРУЗКА ДАННЫХ (кэширование) =====
    try {
        await api.loadData();
        console.log('Данные загружены успешно');
    } catch (err) {
        console.error('Ошибка загрузки данных:', err);
    }

    // ===== СТРАНИЦА КОРЗИНЫ: перерисовка после загрузки данных =====
    if (typeof cart !== 'undefined' && typeof cart.renderPage === 'function') {
        cart.renderPage();
        cart.onCartChange = () => cart.renderPage();
    }

    // ===== ОТПРАВКА ЗАЯВКИ С СТРАНИЦЫ КОРЗИНЫ =====
    const cartForm = document.getElementById('cartForm');
    if (cartForm && typeof cart !== 'undefined') {
        cartForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const phone = document.getElementById('cartPhone')?.value.trim() || '';
            const email = document.getElementById('cartEmail')?.value.trim() || '';
            if (!phone) { cart.showToast('Укажите телефон для связи', true); return; }
            if (!cart.items.length) { cart.showToast('Корзина пуста', true); return; }

            const btn = cartForm.querySelector('button[type="submit"]');
            if (btn) { btn.disabled = true; btn.textContent = 'Отправка…'; }
            try {
                await api.createOrder({
                    phone, email,
                    items: cart.items.map(i => ({ id: i.id, name: i.name, quantity: i.quantity || 1, price: i.price, type: i.type }))
                });
                cart.clear();
                cart.showToast('Заявка отправлена! Мы свяжемся с вами.');
                cart.renderPage();
            } catch (err) {
                cart.showToast('Не удалось отправить заявку', true);
            } finally {
                if (btn) { btn.disabled = false; btn.textContent = 'Отправить заявку'; }
            }
        });
    }
});