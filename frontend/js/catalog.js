class Catalog {
    constructor() {
        this.currentLevel = 1;
        this.currentCategory = null;
        this.currentCondition = null;
        this.filters = {};
        this.searchQuery = '';
        
        this.init();
    }

    async init() {
        // Сначала загружаем данные
        try {
            await api.loadData();
            console.log('Данные загружены в каталоге');
        } catch (err) {
            console.error('Ошибка загрузки данных:', err);
            cart.showToast('Ошибка загрузки каталога', true);
            return;
        }

        // Проверяем URL параметры
        const params = new URLSearchParams(window.location.search);
        const cat = params.get('cat');
        
        if (cat) {
            // Если есть параметр категории, сразу переходим к ней
            await this.selectCategory(cat);
        } else {
            // Иначе показываем все категории
            await this.loadCategories();
        }

        this.setupEventListeners();
    }

    async loadCategories() {
        const categories = await api.getCategories();
        this.renderCategories(categories);
    }

    renderCategories(categories) {
        const grid = document.querySelector('#level1 .categories-grid');
        if (!grid) {
            console.error('Элемент .categories-grid не найден');
            return;
        }

        grid.innerHTML = categories.map(cat => `
            <div class="category-block" data-category="${cat.id}">
                <h3 class="category-block__title">${cat.name}</h3>
                <p class="category-block__desc">${cat.description}</p>
            </div>
        `).join('');

        // Обработчики кликов
        grid.querySelectorAll('.category-block').forEach(block => {
            block.addEventListener('click', () => {
                const catId = block.dataset.category;
                this.selectCategory(catId);
            });
        });
    }

    async selectCategory(categoryId) {
        this.currentCategory = categoryId;
        this.updateBreadcrumbs();

        const category = await api.getCategoryById(categoryId);
        
        if (!category) {
            console.error('Категория не найдена:', categoryId);
            return;
        }

        if (category.hasSubcategories) {
            // Уровень 2: показать Новые/Б/У
            this.showLevel2(category);
        } else {
            // Уровень 3: сразу показать товары
            await this.showLevel3(categoryId, null);
        }
    }

    showLevel2(category) {
        document.getElementById('level1').style.display = 'none';
        document.getElementById('level2').style.display = 'block';
        document.getElementById('globalSearch').style.display = 'none';
        this.currentLevel = 2;

        const grid = document.querySelector('#level2 .subcategories-grid');
        if (!grid) return;

        grid.innerHTML = `
            <div class="category-block" data-condition="new">
                <h3 class="category-block__title">Новые</h3>
                <p class="category-block__desc">Новое оборудование с гарантией производителя</p>
            </div>
            <div class="category-block" data-condition="used">
                <h3 class="category-block__title">Б/У</h3>
                <p class="category-block__desc">Оборудование с пробегом, прошедшее полную диагностику</p>
            </div>
        `;

        grid.querySelectorAll('.category-block').forEach(block => {
            block.addEventListener('click', () => {
                const condition = block.dataset.condition;
                this.selectCondition(condition);
            });
        });
    }

    async selectCondition(condition) {
        this.currentCondition = condition;
        this.updateBreadcrumbs();
        await this.showLevel3(this.currentCategory, condition);
    }

    async showLevel3(categoryId, condition) {
        document.getElementById('level1').style.display = 'none';
        document.getElementById('level2').style.display = 'none';
        document.getElementById('level3').style.display = 'block';
        document.getElementById('globalSearch').style.display = 'none';
        document.getElementById('categorySearch').style.display = 'flex';
        this.currentLevel = 3;

        // Загрузить бренды для фильтров
        const brands = await api.getBrands(categoryId);
        this.renderBrandFilter(brands);

        // Загрузить товары
        await this.loadProducts();
    }

    renderBrandFilter(brands) {
        const select = document.getElementById('filterBrand');
        if (!select) return;
        
        select.innerHTML = '<option value="">Все бренды</option>' + 
            brands.map(b => `<option value="${b}">${b}</option>`).join('');
    }

    async loadProducts() {
        const filters = {
            category: this.currentCategory,
            condition: this.currentCondition,
            ...this.filters,
            search: this.searchQuery
        };

        const sort = document.getElementById('sortSelect')?.value;
        if (sort) filters.sort = sort;

        const products = await api.getProducts(filters);
        this.renderProducts(products);
    }

    renderProducts(products) {
        const grid = document.getElementById('productsGrid');
        const emptyState = document.getElementById('emptyState');

        if (!grid) return;

        if (products.length === 0) {
            grid.style.display = 'none';
            if (emptyState) emptyState.style.display = 'block';
            return;
        }

        grid.style.display = 'grid';
        if (emptyState) emptyState.style.display = 'none';

        grid.innerHTML = products.map(product => `
            <div class="product-card" data-id="${product.id}">
                <div class="product-card__image">
                    <img src="${product.images[0]}" alt="${product.name}">
                    <span class="product-card__badge product-card__badge--${product.condition}">
                        ${product.condition === 'new' ? 'Новый' : 'Б/У'}
                    </span>
                </div>
                <div class="product-card__body">
                    <div class="product-card__brand">${product.brand}</div>
                    <h3 class="product-card__name">${product.name}</h3>
                    <div class="product-card__meta">${product.year} год</div>
                    <div class="product-card__price">${api.formatPrice(product.price)}</div>
                    <div class="product-card__actions">
                        <a href="product.html?id=${product.id}" class="btn btn--secondary btn--sm">Подробнее</a>
                        <button class="btn btn--primary btn--sm add-to-cart" data-id="${product.id}">В корзину</button>
                    </div>
                </div>
            </div>
        `).join('');

        // Обработчики
        grid.querySelectorAll('.add-to-cart').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.stopPropagation();
                const id = parseInt(btn.dataset.id);
                const product = await api.getProductById(id);
                if (product) {
                    cart.add(product, 'purchase');
                }
            });
        });

        grid.querySelectorAll('.product-card').forEach(card => {
            card.addEventListener('click', (e) => {
                if (e.target.closest('.add-to-cart')) return;
                const id = card.dataset.id;
                window.location.href = `product.html?id=${id}`;
            });
        });
    }

    updateBreadcrumbs() {
        const breadcrumbs = document.getElementById('breadcrumbs');
        if (!breadcrumbs) return;

        let html = '<a href="catalog.html">Каталог</a>';

        if (this.currentCategory && api.data) {
            const category = api.data.categories.find(c => c.id === this.currentCategory);
            if (category) {
                html += ` <span class="breadcrumbs__sep">/</span> <span>${category.name}</span>`;
            }
        }

        if (this.currentCondition) {
            const label = this.currentCondition === 'new' ? 'Новые' : 'Б/У';
            html += ` <span class="breadcrumbs__sep">/</span> <span>${label}</span>`;
        }

        breadcrumbs.innerHTML = html;

        // Обновить заголовок
        const title = document.getElementById('catalogTitle');
        if (title) {
            if (this.currentCategory && api.data) {
                const category = api.data.categories.find(c => c.id === this.currentCategory);
                if (category) {
                    title.textContent = category.name;
                }
            } else {
                title.textContent = 'Каталог оборудования';
            }
        }
    }

    setupEventListeners() {
        // Глобальный поиск
        const globalSearchBtn = document.getElementById('globalSearchBtn');
        const globalSearchInput = document.getElementById('globalSearchInput');
        
        if (globalSearchBtn) {
            globalSearchBtn.addEventListener('click', () => {
                this.searchQuery = globalSearchInput.value;
                this.showLevel3(null, null);
            });
        }

        if (globalSearchInput) {
            globalSearchInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    this.searchQuery = e.target.value;
                    this.showLevel3(null, null);
                }
            });
        }

        // Поиск по категории
        const categorySearchBtn = document.getElementById('categorySearchBtn');
        const categorySearchInput = document.getElementById('categorySearchInput');
        
        if (categorySearchBtn) {
            categorySearchBtn.addEventListener('click', () => {
                this.searchQuery = categorySearchInput.value;
                this.loadProducts();
            });
        }

        if (categorySearchInput) {
            categorySearchInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    this.searchQuery = e.target.value;
                    this.loadProducts();
                }
            });
        }

        // Фильтры
        const applyFiltersBtn = document.getElementById('applyFilters');
        const resetFiltersBtn = document.getElementById('resetFilters');
        
        if (applyFiltersBtn) {
            applyFiltersBtn.addEventListener('click', () => {
                this.filters = {
                    brand: document.getElementById('filterBrand').value,
                    yearFrom: document.getElementById('filterYearFrom').value,
                    yearTo: document.getElementById('filterYearTo').value,
                    priceFrom: document.getElementById('filterPriceFrom').value,
                    priceTo: document.getElementById('filterPriceTo').value
                };
                this.loadProducts();
            });
        }

        if (resetFiltersBtn) {
            resetFiltersBtn.addEventListener('click', () => {
                this.filters = {};
                document.getElementById('filterBrand').value = '';
                document.getElementById('filterYearFrom').value = '';
                document.getElementById('filterYearTo').value = '';
                document.getElementById('filterPriceFrom').value = '';
                document.getElementById('filterPriceTo').value = '';
                this.loadProducts();
            });
        }

        // Сортировка
        const sortSelect = document.getElementById('sortSelect');
        if (sortSelect) {
            sortSelect.addEventListener('change', () => {
                this.loadProducts();
            });
        }
    }
}

// Инициализация
document.addEventListener('DOMContentLoaded', () => {
    new Catalog();
});