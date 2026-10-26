/**
 * API-клиент фронтенда.
 * Улучшения: CSRF-токен (заголовок X-CSRF-Token), обработка пагинации
 * ({data, page, total}), понятные ошибки с кодом статуса, кэш категорий.
 */
class Api {
    constructor() {
        this.baseUrl = '/site/backend/api';
        this._csrf = null;
    }

    async request(endpoint, options = {}) {
        const headers = { 'Content-Type': 'application/json', ...options.headers };

        // Для изменяющих запросов прикладываем CSRF-токен
        if (['POST', 'PUT', 'DELETE'].includes((options.method || 'GET').toUpperCase())) {
            const token = await this.getCsrfToken();
            if (token) headers['X-CSRF-Token'] = token;
        }

        const response = await fetch(`${this.baseUrl}/${endpoint}`, {
            ...options,
            headers,
            credentials: 'same-origin',
        });

        let body = {};
        try { body = await response.json(); } catch (e) { /* пустой ответ */ }

        if (!response.ok) {
            const err = new Error(body.error || `HTTP ${response.status}`);
            err.status = response.status;
            throw err;
        }
        return body;
    }

    /** Получает и кэширует CSRF-токен (сессия создаётся на стороне сервера). */
    async getCsrfToken(force = false) {
        if (this._csrf && !force) return this._csrf;
        try {
            const res = await this.request('auth.php?action=csrf-token');
            this._csrf = res.csrf || null;
        } catch (e) {
            this._csrf = null;
        }
        return this._csrf;
    }

    async loadData() {
        // Для совместимости со старым кодом
        if (this._cache) return this._cache;

        const [categories, products] = await Promise.all([
            this.getCategories(),
            this.getProducts()
        ]);

        this._cache = { categories, products };
        return this._cache;
    }

    async getCategories() {
        if (this._categories) return this._categories;
        const categories = await this.request('products.php?action=categories');
        this._categories = categories.map(c => ({
            id: String(c.id),
            name: c.name,
            description: c.description,
            hasSubcategories: Boolean(c.has_subcategories)
        }));
        return this._categories;
    }

    async getCategoryById(id) {
        const categories = await this.getCategories();
        return categories.find(c => c.id === String(id)) || null;
    }

    /** Маппинг сортировки клиента на параметры сервера. */
    static sortParams(sort) {
        switch (sort) {
            case 'price_asc':  return 'sort=price&dir=asc';
            case 'price_desc': return 'sort=price&dir=desc';
            case 'year_desc':  return 'sort=year&dir=desc';
            case 'year_asc':   return 'sort=year&dir=asc';
            case 'name':       return 'sort=name&dir=asc';
            default:           return 'sort=created_at&dir=desc';
        }
    }

    mapProduct(p) {
        return {
            id: p.id,
            name: p.name,
            brand: p.brand,
            year: parseInt(p.year),
            price: parseFloat(p.price),
            condition: p.product_condition,
            category: String(p.category_id),
            description: p.description,
            specs: p.specs || {},
            images: p.images && p.images.length ? p.images : ['assets/images/placeholder.jpg'],
            available: Boolean(p.available)
        };
    }

    async getProducts(filters = {}) {
        const params = new URLSearchParams();

        if (filters.category) params.append('category', filters.category);
        if (filters.condition) params.append('condition', filters.condition);
        if (filters.brand) params.append('brand', filters.brand);
        if (filters.yearFrom) params.append('yearFrom', filters.yearFrom);
        if (filters.yearTo) params.append('yearTo', filters.yearTo);
        if (filters.priceFrom) params.append('priceFrom', filters.priceFrom);
        if (filters.priceTo) params.append('priceTo', filters.priceTo);
        if (filters.search) params.append('search', filters.search);
        if (filters.page) params.append('page', filters.page);
        if (filters.perPage) params.append('per_page', filters.perPage);
        params.append(Api.sortParams(filters.sort));

        const res = await this.request(`products.php?${params}`);

        // Бэкенд может вернуть как массив (старый формат), так и {data, total}
        const list = Array.isArray(res) ? res : (res.data || []);
        const meta = Array.isArray(res) ? {} : res;

        const products = list.map(p => this.mapProduct(p));
        products.total = meta.total ?? products.length;
        products.pages = meta.pages ?? 1;
        products.page  = meta.page ?? 1;
        return products;
    }

    async getProductById(id) {
        try {
            const p = await this.request(`products.php?action=product&id=${encodeURIComponent(id)}`);
            if (!p || p.error) return null;
            return this.mapProduct(p);
        } catch (e) {
            if (e.status === 404) return null;
            throw e;
        }
    }

    async getBrands(category = null) {
        const products = await this.getProducts(category ? { category } : {});
        const brands = [...new Set(products.map(p => p.brand))];
        return brands.sort();
    }

    async getRentalInfo() {
        // Пока возвращаем статические данные
        return {
            short_term: {
                title: "Краткосрочная аренда",
                description: "Аренда на срок от 1 дня до 3 месяцев.",
                conditions: [
                    "Минимальный срок — 1 день",
                    "Максимальный срок — 3 месяца",
                    "Залог — 30% от стоимости аппарата",
                    "Доставка и установка включены",
                    "Техническая поддержка 24/7"
                ],
                pricing: "от 5 000 ₽/день"
            },
            long_term: {
                title: "Долгосрочная аренда",
                description: "Аренда на срок от 3 месяцев до 3 лет.",
                conditions: [
                    "Минимальный срок — 3 месяца",
                    "Максимальный срок — 3 года",
                    "Залог — 15% от стоимости аппарата",
                    "Ежемесячное ТО включено",
                    "Возможность выкупа по окончании аренды"
                ],
                pricing: "от 80 000 ₽/месяц"
            }
        };
    }

    async getRentalProducts() {
        // Для аренды — только УЗИ-аппараты (категория 1)
        return await this.getProducts({ category: '1' });
    }

    async createOrder(orderData) {
        // Сервер сам пересчитает сумму по ценам из БД; поле website — honeypot
        const payload = { ...orderData, website: '' };
        try {
            return await this.request('orders.php?action=create', {
                method: 'POST',
                body: JSON.stringify(payload)
            });
        } catch (err) {
            // 403 — токен протух: обновляем один раз и повторяем
            if (err.status === 403) {
                await this.getCsrfToken(true);
                return this.request('orders.php?action=create', {
                    method: 'POST',
                    body: JSON.stringify({ ...payload })
                });
            }
            throw err;
        }
    }

    formatPrice(price) {
        return new Intl.NumberFormat('ru-RU').format(price) + ' ₽';
    }
}

const api = new Api();
