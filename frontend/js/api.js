class Api {
    constructor() {
        this.baseUrl = '/site/backend/api';
    }

    async request(endpoint, options = {}) {
        try {
            const response = await fetch(`${this.baseUrl}/${endpoint}`, {
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
            throw error;
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
            images: p.images && p.images.length ? p.images : ['assets/images/placeholder.jpg'],
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
            images: p.images && p.images.length ? p.images : ['assets/images/placeholder.jpg'],
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