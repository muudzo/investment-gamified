/**
 * Shared API Service for Investment App
 * Handles all backend communication
 */
class InvestmentApi {
    constructor(baseUrl) {
        this.baseUrl = baseUrl.endsWith('/') ? baseUrl.slice(0, -1) : baseUrl;
        this.token = localStorage.getItem('authToken');
    }

    /**
     * Update the auth token
     */
    setToken(token) {
        this.token = token;
        localStorage.setItem('authToken', token);
    }

    /**
     * Clear the auth token
     */
    clearToken() {
        this.token = null;
        localStorage.removeItem('authToken');
    }

    /**
     * Get headers for requests
     */
    getHeaders(authenticated = true) {
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        };

        if (authenticated && this.token) {
            headers['Authorization'] = `Bearer ${this.token}`;
        }

        return headers;
    }

    /**
     * Login user
     */
    async login(email, password) {
        try {
            const response = await fetch(`${this.baseUrl}/auth/login`, {
                method: 'POST',
                headers: this.getHeaders(false),
                body: JSON.stringify({ email, password })
            });
            const data = await response.json();
            if (data.success) {
                this.setToken(data.token);
            }
            return data;
        } catch (error) {
            console.error('Login error:', error);
            return { success: false, message: error.message };
        }
    }

    /**
     * Register new user
     */
    async register(name, email, password, passwordConfirmation) {
        try {
            const response = await fetch(`${this.baseUrl}/auth/register`, {
                method: 'POST',
                headers: this.getHeaders(false),
                body: JSON.stringify({
                    name,
                    email,
                    password,
                    password_confirmation: passwordConfirmation
                })
            });
            const data = await response.json();
            if (data.success) {
                this.setToken(data.token);
            }
            return data;
        } catch (error) {
            console.error('Registration error:', error);
            return { success: false, message: error.message };
        }
    }

    /**
     * Get user portfolio summary
     */
    async getSummary() {
        try {
            const response = await fetch(`${this.baseUrl}/portfolio/summary`, {
                headers: this.getHeaders()
            });
            return await response.json();
        } catch (error) {
            console.error('Error fetching summary:', error);
            return { success: false, message: error.message };
        }
    }

    /**
     * Get available stocks
     */
    async getStocks() {
        try {
            const response = await fetch(`${this.baseUrl}/stocks`, {
                headers: this.getHeaders(false) // Public endpoint usually, but check if auth needed
            });
            return await response.json();
        } catch (error) {
            console.error('Error fetching stocks:', error);
            return { success: false, message: error.message };
        }
    }

    /**
     * Get single stock details
     */
    async getStock(symbol) {
        try {
            const response = await fetch(`${this.baseUrl}/stocks/${symbol}`, {
                headers: this.getHeaders(false)
            });
            return await response.json();
        } catch (error) {
            console.error('Error fetching stock:', error);
            return { success: false, message: error.message };
        }
    }

    /**
     * Get user portfolio
     */
    async getPortfolio() {
        try {
            const response = await fetch(`${this.baseUrl}/portfolio`, {
                headers: this.getHeaders()
            });
            return await response.json();
        } catch (error) {
            console.error('Error fetching portfolio:', error);
            return { success: false, message: error.message };
        }
    }

    /**
     * Get achievements
     */
    async getAchievements() {
        try {
            const response = await fetch(`${this.baseUrl}/achievements`, {
                headers: this.getHeaders()
            });
            return await response.json();
        } catch (error) {
            console.error('Error fetching achievements:', error);
            return { success: false, message: error.message };
        }
    }

    /**
     * Buy stock
     */
    async buyStock(symbol, quantity) {
        return this._trade('buy', symbol, quantity);
    }

    /**
     * Sell stock
     */
    async sellStock(symbol, quantity) {
        return this._trade('sell', symbol, quantity);
    }

    /**
     * Internal trade method
     */
    async _trade(type, symbol, quantity) {
        try {
            const response = await fetch(`${this.baseUrl}/portfolio/${type}`, {
                method: 'POST',
                headers: this.getHeaders(),
                body: JSON.stringify({
                    stock_symbol: symbol,
                    quantity: parseInt(quantity)
                })
            });
            return await response.json();
        } catch (error) {
            console.error(`Error ${type}ing stock:`, error);
            return { success: false, message: error.message };
        }
    }
}

// Expose to window
window.InvestmentApi = InvestmentApi;
