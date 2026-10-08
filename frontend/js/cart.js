/**
 * Модуль корзины
 * Хранит данные в localStorage, синхронизирует между вкладками
 */
class Cart {
    constructor() {
        this.storageKey = 'meduzi_cart';
        this.items = this.load();
        this.updateCount();

        // Синхронизация между вкладками
        window.addEventListener('storage', (e) => {
            if (e.key === this.storageKey) {
                this.items = this.load();
                this.updateCount();
                this.onCartChange();
            }
        });
    }

    /**
     * Загрузить корзину из localStorage
     */
    load() {
        try {
            const data = localStorage.getItem(this.storageKey);
            return data ? JSON.parse(data) : [];
        } catch (e) {
            console.error('Cart load error:', e);
            return [];
        }
    }

    /**
     * Сохранить корзину в localStorage
     */
    save() {
        localStorage.setItem(this.storageKey, JSON.stringify(this.items));
        this.updateCount();
        this.onCartChange();
    }

    /**
     * Добавить товар в корзину
     * @param {Object} product - объект товара
     * @param {string} type - 'purchase' | 'rental' | 'repair'
     */
    add(product, type = 'purchase') {
        // Проверяем, нет ли уже такого товара с таким же типом
        const existing = this.items.find(
            item => item.id === product.id && item.type === type
        );

        if (existing) {
            existing.quantity = (existing.quantity || 1) + 1;
        } else {
            this.items.push({
                id: product.id,
                name: product.name,
                brand: product.brand,
                price: product.price,
                condition: product.condition,
                image: product.images && product.images[0] ? product.images[0] : '',
                type: type,
                quantity: 1
            });
        }

        this.save();
        this.showToast(`${product.name} добавлен в корзину`);
    }

    /**
     * Удалить товар из корзины
     */
    remove(index) {
        this.items.splice(index, 1);
        this.save();
    }

    /**
     * Изменить количество
     */
    updateQuantity(index, quantity) {
        if (quantity <= 0) {
            this.remove(index);
            return;
        }
        this.items[index].quantity = quantity;
        this.save();
    }

    /**
     * Очистить корзину
     */
    clear() {
        this.items = [];
        this.save();
    }

    /**
     * Получить все товары
     */
    getItems() {
        return this.items;
    }

    /**
     * Получить количество товаров
     */
    getCount() {
        return this.items.reduce((sum, item) => sum + (item.quantity || 1), 0);
    }

    /**
     * Получить итоговую сумму (только для покупки)
     */
    getTotal() {
        return this.items
            .filter(item => item.type === 'purchase')
            .reduce((sum, item) => sum + item.price * (item.quantity || 1), 0);
    }

    /**
     * Получить товары по типу
     */
    getItemsByType(type) {
        return this.items.filter(item => item.type === type);
    }

    /**
     * Обновить счётчик в шапке
     */
    updateCount() {
        const countEl = document.getElementById('cartCount');
        if (countEl) {
            const count = this.getCount();
            countEl.textContent = count;
            countEl.style.display = count > 0 ? 'flex' : 'none';
        }
    }

    /**
     * Показать toast-уведомление
     */
    showToast(message, isError = false) {
        const container = document.getElementById('toastContainer');
        if (!container) return;

        const toast = document.createElement('div');
        toast.className = `toast${isError ? ' toast--error' : ''}`;
        toast.textContent = message;
        container.appendChild(toast);

        setTimeout(() => {
            if (toast.parentNode) toast.parentNode.removeChild(toast);
        }, 3000);
    }

    /**
     * Коллбэк при изменении корзины (можно переопределить)
     */
    onCartChange() {
        // Переопределяется на странице корзины для перерисовки
    }

    /**
     * Получить тип в виде текста
     */
    getTypeLabel(type) {
        const labels = {
            purchase: 'Покупка',
            rental: 'Аренда',
            repair: 'Ремонт'
        };
        return labels[type] || type;
    }

    /* ===== Рендеринг страницы корзины (cart.html) ===== */

    renderPage() {
        const itemsEl = document.getElementById('cartItems');
        if (!itemsEl) return; // не на странице корзины

        const emptyEl = document.getElementById('cartEmptyState');
        const summaryEl = document.getElementById('cartSummaryContent');
        const items = this.items;

        if (!items.length) {
            itemsEl.innerHTML = '';
            if (summaryEl) summaryEl.innerHTML = '';
            if (emptyEl) emptyEl.style.display = 'block';
            return;
        }
        if (emptyEl) emptyEl.style.display = 'none';

        itemsEl.innerHTML = items.map((item, i) => `
            <div class="cart-item" data-index="${i}">
                <div class="cart-item__image">
                    ${item.image ? `<img src="${item.image}" alt="${item.name}">` : ''}
                </div>
                <div class="cart-item__info">
                    <h4 class="cart-item__title">${item.name}</h4>
                    <p class="cart-item__meta">${item.brand || ''} ${item.year ? '· ' + item.year : ''} · ${this.getTypeLabel(item.type)}</p>
                    ${item.rentalPeriod ? `<p class="cart-item__meta">Срок аренды: ${item.rentalPeriod}</p>` : ''}
                </div>
                <div class="cart-item__controls">
                    <input type="number" class="qty-input" min="1" value="${item.quantity || 1}" data-index="${i}" aria-label="Количество">
                    <span class="cart-item__price">${api.formatPrice((item.price || 0) * (item.quantity || 1))}</span>
                    <button class="cart-item__remove" data-index="${i}" aria-label="Удалить">&times;</button>
                </div>
            </div>
        `).join('');

        if (summaryEl) {
            const total = this.getTotal();
            summaryEl.innerHTML = `
                <div class="cart-summary__row"><span>Товаров:</span><strong>${this.getCount()} шт.</strong></div>
                <div class="cart-summary__row cart-summary__row--total"><span>Итого (покупка):</span><strong>${api.formatPrice(total)}</strong></div>
            `;
        }

        // Делегирование событий
        itemsEl.onclick = (e) => {
            const rm = e.target.closest('.cart-item__remove');
            if (rm) { this.remove(+rm.dataset.index); this.renderPage(); return; }
            const qty = e.target.closest('.qty-input');
            if (qty) {
                this.updateQuantity(+qty.dataset.index, Math.max(1, parseInt(qty.value) || 1));
                this.renderPage();
            }
        };
    }
}

// Глобальный экземпляр
const cart = new Cart();