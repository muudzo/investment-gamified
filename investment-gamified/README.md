<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

---

## Modular refactor (recent changes)

I've started a modular refactor to make the codebase easier to maintain and extend:

- Controllers have been moved out of top-level files and into proper controller classes under `app/Http/Controllers/Api`.
- Business logic has been extracted into services under `app/Services` (e.g. `PortfolioService`) so controllers remain thin and easier to test.

How to extend:

1. Add or extend controllers in `app/Http/Controllers/Api`.
2. Move complex business logic into `app/Services` and inject the service into controllers via constructor injection.
3. Keep route definitions in `routes.php` (or `routes/api.php` if you prefer) and point them to controller classes.

If you want, I can continue: extract more logic into services, add a repository layer, and add unit tests for services. ✅

## API testing — quick start (auth + third-party stock APIs) 🔧

1. Make sure you configured your API keys in `.env`:

	 - ALPHAVANTAGE_API_KEY=your_alpha_vantage_key
	 - FMP_API_KEY=your_fmp_key

2. Start the app locally (example):

```bash
php artisan serve
```

3. Test auth endpoints (register/login/logout):

Register:
```bash
curl -s -X POST http://localhost:8000/api/auth/register \
	-H 'Content-Type: application/json' \
	-d '{ "name": "Test User", "email": "test@example.com", "password": "password123", "password_confirmation": "password123" }'
```

Login:
```bash
curl -s -X POST http://localhost:8000/api/auth/login \
	-H 'Content-Type: application/json' \
	-d '{ "email": "test@example.com", "password": "password123" }'
```

Use the returned token with the `Authorization: Bearer <token>` header for protected routes.

4. Test external third-party stock endpoints (AlphaVantage / FMP):

Get a quote (FMP):
```bash
curl -s "http://localhost:8000/api/external/stocks/quote/AAPL?source=fmp"
```

Get a quote (AlphaVantage):
```bash
curl -s "http://localhost:8000/api/external/stocks/quote/AAPL?source=alphavantage"
```

Search (must include query):
```bash
curl -s "http://localhost:8000/api/external/stocks/search?q=apple&source=alphavantage"
```

Get company profile (FMP only):
```bash
curl -s "http://localhost:8000/api/external/stocks/profile/AAPL"
```

Notes:
- The endpoints return 502 status if the provider fails to return data (e.g., rate limits). Check your keys and the provider quotas.
- The console commands `php artisan stocks:update-prices` and `php artisan stocks:import-history` are also available — be careful with rate limits.
