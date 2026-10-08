class RentalDetails {
    constructor() {
        this.type = null;
        this.init();
    }

    async init() {
        const params = new URLSearchParams(window.location.search);
        this.type = params.get('type');

        if (!this.type || !['short_term', 'long_term'].includes(this.type)) {
            window.location.href = 'rental.html';
            return;
        }

        await this.loadRentalInfo();
        await this.loadRentalProducts();
    }

    async loadRentalInfo() {
        const rentalInfo = await api.getRentalInfo();
        const info = rentalInfo[this.type];

        document.getElementById('rentalTypeTitle').textContent = info.title;
        document.getElementById('rentalTitle').textContent = info.title;
        document.getElementById('rentalDescription').textContent = info.description;
        document.getElementById('rentalPricing').textContent = info.pricing;

        const conditionsList = document.getElementById('rentalConditions');
        conditionsList.innerHTML = info.conditions.map(c => `<li>${c}</li>`).join('');
    }

    async loadRentalProducts() {
        const products = await api.getRentalProducts();
        const grid = document.getElementById('rentalProducts');

        grid.innerHTML = products.map(product => `
            <div class="product-card">
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
                        <button class="btn btn--primary btn--sm add-to-rental" data-id="${product.id}">В корзину (аренда)</button>
                    </div>
                </div>
            </div>
        `).join('');

        grid.querySelectorAll('.add-to-rental').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.stopPropagation();
                const id = parseInt(btn.dataset.id);
                const product = await api.getProductById(id);
                cart.add(product, 'rental');
            });
        });
    }
}

document.addEventListener('DOMContentLoaded', () => {
    new RentalDetails();
});