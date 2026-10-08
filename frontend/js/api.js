/* Демо-данные — используются, когда PHP-бэкенд недоступен
   (например, при статичном предпросмотре через python -m http.server).
   В боевом окружении запросы уходят на реальный API. */
const DEMO_CATEGORIES = [
    { id: 1, name: 'УЗИ-аппараты', description: 'Стационарные и портативные ультразвуковые сканеры', has_subcategories: 1 },
    { id: 2, name: 'Датчики', description: 'Конвексные, линейные, внутриполостные и допплеровские датчики', has_subcategories: 1 },
    { id: 3, name: 'Расходные материалы', description: 'Гели, плёнки, салфетки и средства дезинфекции', has_subcategories: 0 },
    { id: 4, name: 'Медицинская мебель', description: 'Кушетки, тележки и стойки для УЗИ-оборудования', has_subcategories: 0 },
];

const DEMO_PRODUCTS = [
    { id: 1, name: 'Mindray Resona 7', brand: 'Mindray', year: 2022, price: 8500000, product_condition: 'new', category_id: 1, available: 1,
      description: 'Флагманский стационарный УЗИ-аппарат с технологией eFlow для исследований высокой точности.',
      specs: { 'Тип': 'Стационарный', 'Монитор': '21.3"', 'Каналы': '192', 'Режимы': 'B, M, CFM, PW, HD-Flow' }, images: [] },
    { id: 2, name: 'Mindray DC-8 (Анализ)', brand: 'Mindray', year: 2020, price: 4200000, product_condition: 'used', category_id: 1, available: 1,
      description: 'Специализированный аппарат для дуплексного сканирования сосудов. Пройдёт полную диагностику перед отгрузкой.',
      specs: { 'Тип': 'Стационарный', 'Монитор': '19"', 'Каналы': '96', 'Комплект': '3 датчика' }, images: [] },
    { id: 3, name: 'Butterfly iQ3', brand: 'Butterfly', year: 2023, price: 780000, product_condition: 'new', category_id: 1, available: 1,
      description: 'Портативный карманный УЗИ-сканер, работающий со смартфоном. 9 режимов визуализации на чипе.',
      specs: { 'Тип': 'Портативный', 'Вес': '360 г', 'Совместимость': 'iOS / Android', 'Датчик': '5-в-1' }, images: [] },
    { id: 4, name: 'Mindray M7 (Converge)', brand: 'Mindray', year: 2021, price: 1650000, product_condition: 'used', category_id: 1, available: 1,
      description: 'Планшетный портативный сканер с беспроводными датчиками. Идеален для выездных исследований.',
      specs: { 'Тип': 'Планшетный', 'Монитор': '13.3"', 'Аккумулятор': '4 часа', 'Wi-Fi': 'Да' }, images: [] },
    { id: 5, name: 'GE Vivid E95', brand: 'GE', year: 2021, price: 9800000, product_condition: 'new', category_id: 1, available: 0,
      description: 'Кардиологическая система премиум-класса с AIR Code BI и автоматическими измерениями.',
      specs: { 'Тип': 'Стационарный', 'Монитор': '21"', 'Каналы': '1024', 'Облако': 'GE Xcelera' }, images: [] },
    { id: 6, name: 'HL3-12iU Линейный датчик', brand: 'Mindray', year: 2022, price: 245000, product_condition: 'new', category_id: 2, available: 1,
      description: 'Высокочастотный линейный датчик для поверхностных структур и сосудистых исследований.',
      specs: { 'Частота': '3–12 МГц', 'Контакт': 'L12-4s', 'Стерилизация': 'Да' }, images: [] },
    { id: 7, name: 'C5-1U Конвексный датчик', brand: 'Mindray', year: 2020, price: 189000, product_condition: 'used', category_id: 2, available: 1,
      description: 'Б/у конвексный датчик в отличном состоянии, проверен на дефекты элементов.',
      specs: { 'Частота': '5–1 МГц', 'Применение': 'Абдомен, акушерство' }, images: [] },
    { id: 8, name: 'УЗИ-гель Aquasonic 500 мл ×24', brand: 'Parker', year: 2024, price: 12500, product_condition: 'new', category_id: 3, available: 1,
      description: 'Гипоаллергенный универсальный гель для всех видов ультразвуковых исследований. Упаковка 24 флакона.',
      specs: { 'Объём': '500 мл', 'Количество': '24 шт', 'Срок годности': '3 года' }, images: [] },
    { id: 9, name: 'Тележка для УЗИ-аппарата MedStil', brand: 'MedStil', year: 2023, price: 68000, product_condition: 'new', category_id: 4, available: 1,
      description: 'Медицинская тележка с регулировкой высоты, полкой для датчиков и фиксацией монитора.',
      specs: { 'Нагрузка': 'до 40 кг', 'Высота': '90–120 см', 'Колёса': 'с тормозом' }, images: [] },
];

class Api {
    constructor() {
        // Базовый путь к PHP API. Если бэкенд недоступен — включается демо-режим.
        this.endpoints = ['/site/backend/api', '../backend/api', '/backend/api'];
        this._baseUrl = null;
        this._demoMode = false;
    }

    /** Попытка найти рабочий эндпоинт (кэш результата) */
    async resolveBaseUrl() {
        if (this._resolvePromise) return this._resolvePromise;
        this._resolvePromise = (async () => {
            // Демо-режим включается автоматически, если:
            // 1) страница открыта без PHP (file:// или статический сервер), или
            // 2) ни один из эндпоинтов не отвечает валидным JSON.
            const isStaticHost = location.protocol === 'file:'
                || /(^|\.)localhost$/.test(location.hostname)
                || /^127(\.\d+){3}$/.test(location.hostname);
            if (isStaticHost) {
                this._demoMode = true;
                console.info('[API] Статический предпросмотр — включён демо-режим с локальными данными.');
                return null;
            }
            for (const base of this.endpoints) {
                try {
                    const r = await fetch(`${base}/products.php?action=categories`, { method: 'GET' });
                    const ct = r.headers.get('content-type') || '';
                    if (r.ok && ct.includes('json')) {
                        return base;
                    }
                } catch (e) { /* пробуем следующий */ }
            }
            this._demoMode = true;
            console.info('[API] Бэкенд недоступен — включён демо-режим с локальными данными.');
            return null;
        })();
        return this._resolvePromise;
    }

    /** Демо-ответ в формате PHP API */
    _demoResponse(endpoint) {
        const url = new URL(endpoint, 'http://localhost');
        const path = url.pathname.split('/').pop();
        const action = url.searchParams.get('action');
        const delay = (ms) => new Promise(res => setTimeout(res, ms));

        if (path === 'products.php') {
            if (action === 'categories') return delay(250).then(() => DEMO_CATEGORIES.map(c => ({ ...c })));
            if (action === 'product') {
                const p = DEMO_PRODUCTS.find(x => x.id == url.searchParams.get('id'));
                return delay(200).then(() => p ? ({ ...p }) : { error: 'Product not found' });
            }
            let list = DEMO_PRODUCTS.map(p => ({ ...p }));
            const f = (k, fn) => { const v = url.searchParams.get(k); if (v) list = list.filter(fn(v)); };
            f('category', v => p => p.category_id == v);
            f('condition', v => p => p.product_condition === v);
            f('brand', v => p => p.brand === v);
            f('yearFrom', v => p => p.year >= +v);
            f('yearTo', v => p => p.year <= +v);
            f('priceFrom', v => p => p.price >= +v);
            f('priceTo', v => p => p.price <= +v);
            f('search', v => p => (p.name + ' ' + p.description + ' ' + p.brand).toLowerCase().includes(v.toLowerCase()));
            return delay(300).then(() => list);
        }
        if (path === 'orders.php' && action === 'create') {
            return delay(400).then(() => ({ success: true, id: Math.floor(Math.random() * 900) + 100 }));
        }
        return delay(200).then(() => []);
    }

    async request(endpoint, options = {}) {
        const base = await this.resolveBaseUrl();
        if (!base) {
            return this._demoResponse(endpoint);
        }

        try {
            const response = await fetch(`${base}/${endpoint}`, {
                ...options,
                headers: {
                    'Content-Type': 'application/json',
                    ...options.headers
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            return await response.json();
        } catch (error) {
            console.error('API Error:', error);
            // Сетевая ошибка во время работы — падаем в демо-режим, чтобы сайт не «ломался»
            this._demoMode = true;
            return this._demoResponse(endpoint);
        }
    }

    async loadData() {
        // Для совместимости со старым кодом
        if (this._cache) return this._cache;

        const [categories, products] = await Promise.all([
            this.getCategories(),
            this.getProducts()
        ]);

        this._cache = { categories, products };
        // Публичный геттер — страницы используют api.data после загрузки
        this.data = this._cache;
        return this._cache;
    }

    async getCategories() {
        const categories = await this.request('products.php?action=categories');
        // Приводим к формату фронтенда
        return categories.map(c => ({
            id: String(c.id),
            name: c.name,
            description: c.description,
            hasSubcategories: Boolean(c.has_subcategories)
        }));
    }

    async getCategoryById(id) {
        const categories = await this.getCategories();
        return categories.find(c => c.id === String(id)) || null;
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
        
        let products = await this.request(`products.php?${params}`);
        
        // Приводим к формату фронтенда
        products = products.map(p => ({
            id: p.id,
            name: p.name,
            brand: p.brand,
            year: parseInt(p.year),
            price: parseFloat(p.price),
            condition: p.product_condition,
            category: String(p.category_id),
            description: p.description,
            specs: p.specs || {},
            images: p.images && p.images.length ? p.images : ['data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 400 300%27%3E%3Crect width=%27400%27 height=%27300%27 fill=%27%23eef2f7%27/%3E%3Ccircle cx=%27200%27 cy=%27125%27 r=%2746%27 fill=%27none%27 stroke=%27%23b9c6d6%27 stroke-width=%2710%27/%3E%3Cline x1=%27233%27 y1=%27158%27 x2=%27272%27 y2=%27197%27 stroke=%27%23b9c6d6%27 stroke-width=%2714%27 stroke-linecap=%27round%27/%3E%3Ctext x=%27200%27 y=%27252%27 font-family=%27Arial%27 font-size=%2719%27 fill=%27%238fa1b3%27 text-anchor=%27middle%27%3EUZI%3C/text%3E%3C/svg%3E'],
            available: Boolean(p.available)
        }));
        
        // Сортировка на клиенте
        if (filters.sort) {
            switch (filters.sort) {
                case 'price_asc': products.sort((a, b) => a.price - b.price); break;
                case 'price_desc': products.sort((a, b) => b.price - a.price); break;
                case 'year_desc': products.sort((a, b) => b.year - a.year); break;
                case 'year_asc': products.sort((a, b) => a.year - b.year); break;
                case 'name': products.sort((a, b) => a.name.localeCompare(b.name)); break;
            }
        }
        
        return products;
    }

    async getProductById(id) {
        const p = await this.request(`products.php?action=product&id=${id}`);
        if (!p || p.error) return null;
        
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
            images: p.images && p.images.length ? p.images : ['data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 400 300%27%3E%3Crect width=%27400%27 height=%27300%27 fill=%27%23eef2f7%27/%3E%3Ccircle cx=%27200%27 cy=%27125%27 r=%2746%27 fill=%27none%27 stroke=%27%23b9c6d6%27 stroke-width=%2710%27/%3E%3Cline x1=%27233%27 y1=%27158%27 x2=%27272%27 y2=%27197%27 stroke=%27%23b9c6d6%27 stroke-width=%2714%27 stroke-linecap=%27round%27/%3E%3Ctext x=%27200%27 y=%27252%27 font-family=%27Arial%27 font-size=%2719%27 fill=%27%238fa1b3%27 text-anchor=%27middle%27%3EUZI%3C/text%3E%3C/svg%3E'],
            available: Boolean(p.available)
        };
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
        return await this.request('orders.php?action=create', {
            method: 'POST',
            body: JSON.stringify(orderData)
        });
    }

    formatPrice(price) {
        return new Intl.NumberFormat('ru-RU').format(price) + ' ₽';
    }
}

const api = new Api();