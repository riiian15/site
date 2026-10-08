/**
 * Модуль страницы товара
 */
class ProductPage {
    constructor() {
        this.productId = null;
        this.init();
    }

    async init() {
        const params = new URLSearchParams(window.location.search);
        this.productId = params.get('id');

        if (!this.productId) {
            window.location.href = 'catalog.html';
            return;
        }

        await this.loadProduct();
        this.setupEventListeners();
    }

    async loadProduct() {
        const product = await api.getProductById(this.productId);
        
        if (!product) {
            window.location.href = 'catalog.html';
            return;
        }

        this.renderProduct(product);
        this.renderBreadcrumbs(product);
    }

    renderProduct(product) {
        document.getElementById('mainImage').src = product.images[0];
        document.getElementById('mainImage').alt = product.name;
        document.getElementById('productBrand').textContent = product.brand;
        document.getElementById('productName').textContent = product.name;
        document.getElementById('productYear').textContent = `${product.year} год`;
        document.getElementById('productCondition').textContent = product.condition === 'new' ? 'Новый' : 'Б/У';
        document.getElementById('productPrice').textContent = api.formatPrice(product.price);
        document.getElementById('productDescription').textContent = product.description;

        // Характеристики
        const specsGrid = document.getElementById('productSpecs');
        specsGrid.innerHTML = Object.entries(product.specs).map(([key, value]) => `
            <div class="product-specs__item">
                <div class="product-specs__label">${key}</div>
                <div class="product-specs__value">${value}</div>
            </div>
        `).join('');
    }

    renderBreadcrumbs(product) {
        const category = api.data.categories.find(c => c.id === product.category);
        const breadcrumbs = document.getElementById('productBreadcrumbs');
        
        let html = '<a href="catalog.html">Каталог</a>';
        html += ` <span class="breadcrumbs__sep">/</span> <a href="catalog.html?cat=${product.category}">${category.name}</a>`;
        
        if (category.hasSubcategories) {
            const conditionLabel = product.condition === 'new' ? 'Новые' : 'Б/У';
            html += ` <span class="breadcrumbs__sep">/</span> <span>${conditionLabel}</span>`;
        }
        
        html += ` <span class="breadcrumbs__sep">/</span> <span>${product.name}</span>`;
        
        breadcrumbs.innerHTML = html;
    }

    setupEventListeners() {
        document.getElementById('addToCartBtn')?.addEventListener('click', async () => {
            const product = await api.getProductById(this.productId);
            cart.add(product, 'purchase');
        });
    }
}

// Инициализация
document.addEventListener('DOMContentLoaded', () => {
    new ProductPage();
});