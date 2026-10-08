/**
 * Модуль валидации и обработки форм
 */
class FormHandler {
    /**
     * Валидация имени
     */
    static validateName(name) {
        if (!name || name.trim().length < 2) {
            return 'Введите имя (минимум 2 символа)';
        }
        return '';
    }

    /**
     * Валидация телефона
     */
    static validatePhone(phone) {
        if (!phone) return 'Введите номер телефона';
        const cleaned = phone.replace(/\D/g, '');
        if (cleaned.length < 10) return 'Введите корректный номер телефона';
        return '';
    }

    /**
     * Валидация email (опционально)
     */
    static validateEmail(email) {
        if (!email) return ''; // необязательное поле
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!re.test(email)) return 'Введите корректный e-mail';
        return '';
    }

    /**
     * Маска телефона
     */
    static phoneMask(input) {
        input.addEventListener('input', function (e) {
            let val = this.value.replace(/\D/g, '');
            if (val.length === 0) {
                this.value = '';
                return;
            }
            if (val[0] === '8') val = '7' + val.slice(1);
            if (val[0] !== '7') val = '7' + val;

            let formatted = '+7';
            if (val.length > 1) formatted += ' (' + val.slice(1, 4);
            if (val.length > 4) formatted += ') ' + val.slice(4, 7);
            if (val.length > 7) formatted += '-' + val.slice(7, 9);
            if (val.length > 9) formatted += '-' + val.slice(9, 11);

            this.value = formatted;
        });

        input.addEventListener('focus', function () {
            if (!this.value) this.value = '+7';
        });

        input.addEventListener('blur', function () {
            if (this.value === '+7') this.value = '';
        });
    }

    /**
     * Показать ошибку поля
     */
    static showError(inputId, errorId, message) {
        const input = document.getElementById(inputId);
        const error = document.getElementById(errorId);
        if (message) {
            if (input) input.classList.add('error');
            if (error) error.textContent = message;
            return false;
        } else {
            if (input) input.classList.remove('error');
            if (error) error.textContent = '';
            return true;
        }
    }

    /**
     * Обработка отправки формы заявки
     */
    static async submitRequest(formElement) {
        const formData = new FormData(formElement);
        const data = Object.fromEntries(formData.entries());

        // Проверка honeypot
        if (data.website) {
            console.log('Spam detected');
            return false;
        }

        // Валидация
        let isValid = true;

        const nameError = FormHandler.validateName(data.name);
        if (!FormHandler.showError('reqName', 'reqNameError', nameError)) isValid = false;

        const phoneError = FormHandler.validatePhone(data.phone);
        if (!FormHandler.showError('reqPhone', 'reqPhoneError', phoneError)) isValid = false;

        if (data.email) {
            const emailError = FormHandler.validateEmail(data.email);
            if (!FormHandler.showError('reqEmail', 'reqEmailError', emailError)) isValid = false;
        }

        if (!isValid) return false;

        // Формируем данные заявки
        const request = {
            ...data,
            cartItems: cart.getItems(),
            timestamp: new Date().toISOString()
        };

        console.log('Отправка заявки:', request);

        // TODO: Заменить на реальный fetch к PHP API
        // const response = await fetch('/api/request', {
        //     method: 'POST',
        //     headers: { 'Content-Type': 'application/json' },
        //     body: JSON.stringify(request)
        // });

        // Пока имитация успешной отправки
        return new Promise(resolve => {
            setTimeout(() => {
                cart.showToast('Заявка успешно отправлена! Мы свяжемся с вами в ближайшее время.');
                formElement.reset();
                cart.clear();
                resolve(true);
            }, 500);
        });
    }

    /**
     * Инициализация формы на главной странице
     */
    static initMainForm() {
        const form = document.getElementById('mainRequestForm');
        if (!form) return;

        // Маска телефона
        const phoneInput = document.getElementById('reqPhone');
        if (phoneInput) FormHandler.phoneMask(phoneInput);

        // Отправка
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