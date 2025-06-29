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

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

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

# Calendar API

A Laravel-based backend API for managing calendar events, designed to integrate seamlessly with FullCalendar and Google Calendar. This project supports user authentication, event CRUD operations, and Google OAuth integration.

---

## Features

- **User Authentication** (Laravel Sanctum/JWT)
- **Event Management**: Create, read, update, and delete events
- **Google Calendar Sync**: Import/export events with Google Calendar
- **FullCalendar Integration**: Designed for use with modern frontend frameworks (e.g., Vue.js)
- **Rich Event Fields**: Supports recurrence, attendees, reminders, and more

---

## Getting Started

### Prerequisites

- PHP 8.1+
- Composer
- MySQL or compatible database
- Node.js & npm (for frontend integration)
- Google Cloud Project (for OAuth)

### Installation

1. **Clone the repository**
   ```sh
   git clone https://github.com/your-username/calendarapi.git
   cd calendarapi
   ```

2. **Install dependencies**
   ```sh
   composer install
   ```

3. **Copy and configure environment**
   ```sh
   cp .env.example .env
   # Edit .env with your database and Google credentials
   ```

4. **Generate application key**
   ```sh
   php artisan key:generate
   ```

5. **Run migrations and seeders**
   ```sh
   php artisan migrate --seed
   ```

6. **Start the development server**
   ```sh
   php artisan serve
   ```

---

## Google OAuth Setup

1. Create a project in [Google Cloud Console](https://console.cloud.google.com/).
2. Enable the Google Calendar API.
3. Create OAuth 2.0 credentials and set the redirect URI to:
   ```
   http://127.0.0.1:8888/api/auth/google/callback
   ```
4. Add your `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, and `GOOGLE_REDIRECT` to your `.env` file.

---

## API Endpoints

- `POST /api/login` — User login
- `POST /api/register` — User registration
- `GET /api/events` — List events
- `POST /api/events` — Create event
- `PUT /api/events/{id}` — Update event
- `DELETE /api/events/{id}` — Delete event
- `GET /api/auth/google/redirect` — Start Google OAuth
- `GET /api/auth/google/callback` — Handle Google OAuth callback

---

## Frontend Integration

This API is designed to work with any frontend, such as Vue.js with FullCalendar.  
Set your frontend to communicate with the API at `http://127.0.0.1:8888`.

---

## Contributing

Pull requests are welcome! For major changes, please open an issue first to discuss what you would like to change.

---

## License

This project is open-sourced under the [MIT license](https://opensource.org/licenses/MIT).
