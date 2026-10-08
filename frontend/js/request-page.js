class RequestPage {
    constructor() {
        this.init();
    }

    init() {
        this.setupForm();
    }

    setupForm() {
        const form = document.getElementById('requestForm');
        if (!form) return;

        const phoneInput = document.getElementById('reqPhone');
        if (phoneInput) FormHandler.phoneMask(phoneInput);

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = form.querySelector('button[type="submit"]');
            const originalText = btn.textContent;
            btn.textContent = 'Отправка...';
            btn.disabled = true;

            try {
                await FormHandler.submitRequest(form);
            } catch (err) {
                cart.showToast('Ошибка отправки. Попробуйте позже.', true);
            }

            btn.textContent = originalText;
            btn.disabled = false;
        });
    }
}

document.addEventListener('DOMContentLoaded', () => {
    new RequestPage();
});