/**
 * Модуль роутинга — управление навигацией и историей
 */
class Router {
    constructor() {
        this.routes = {};
        this.currentRoute = null;
    }

    /**
     * Добавить маршрут
     */
    addRoute(path, handler) {
        this.routes[path] = handler;
    }

    /**
     * Получить текущий путь
     */
    getCurrentPath() {
        return window.location.pathname;
    }

    /**
     * Получить параметры URL
     */
    getParams() {
        return new URLSearchParams(window.location.search);
    }

    /**
     * Перейти по маршруту
     */
    navigate(path, params = {}) {
        const url = new URL(path, window.location.origin);
        
        Object.keys(params).forEach(key => {
            url.searchParams.set(key, params[key]);
        });

        window.history.pushState({ path, params }, '', url);
        this.handleRoute();
    }

    /**
     * Обработать текущий маршрут
     */
    handleRoute() {
        const path = this.getCurrentPath();
        const params = this.getParams();

        // Найти подходящий маршрут
        for (let routePath in this.routes) {
            if (path.includes(routePath)) {
                this.currentRoute = routePath;
                this.routes[routePath](params);
                return;
            }
        }
    }

    /**
     * Инициализация роутера
     */
    init() {
        // Обработка кнопок назад/вперед
        window.addEventListener('popstate', () => {
            this.handleRoute();
        });

        // Первоначальная обработка
        this.handleRoute();
    }

    /**
     * Обновить URL без перезагрузки
     */
    updateURL(params = {}) {
        const url = new URL(window.location);
        
        Object.keys(params).forEach(key => {
            if (params[key] === null || params[key] === '') {
                url.searchParams.delete(key);
            } else {
                url.searchParams.set(key, params[key]);
            }
        });

        window.history.replaceState({}, '', url);
    }

    /**
     * Получить значение параметра
     */
    getParam(key) {
        const params = this.getParams();
        return params.get(key);
    }
}

// Глобальный экземпляр
const router = new Router();