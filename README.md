# Calendar API

A Laravel-powered backend API for managing calendar events, designed for seamless integration with FullCalendar and Google Calendar. This project supports user authentication, event CRUD operations, and Google OAuth for calendar synchronization.

---

## Features

-   **User Authentication** (Laravel Sanctum/JWT)
-   **Event Management**: Create, read, update, and delete events
-   **Google Calendar Sync**: Import/export events with Google Calendar
-   **FullCalendar Integration**: Built for use with modern frontends (e.g., Vue.js)
-   **Comprehensive Event Fields**: Supports recurrence, attendees, reminders, and more

---

## Getting Started

### Prerequisites

-   PHP 8.1 or higher
-   Composer
-   MySQL or compatible database
-   Node.js & npm (for frontend integration)
-   Google Cloud Project (for OAuth)

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

-   `POST /api/login` — User login
-   `POST /api/register` — User registration
-   `GET /api/events` — List events
-   `POST /api/events` — Create event
-   `PUT /api/events/{id}` — Update event
-   `DELETE /api/events/{id}` — Delete event
-   `GET /api/auth/google/redirect` — Start Google OAuth
-   `GET /api/auth/google/callback` — Handle Google OAuth callback

---

## Frontend Integration

This API is designed to work with any frontend, such as Vue.js with FullCalendar.  
Set your frontend to communicate with the API at `http://127.0.0.1:8888`.

---

## Contributing

Pull requests are welcome! For major changes, please open an issue first to discuss your ideas.

---

## License

This project is open-sourced under the [MIT license](https://opensource.org/licenses/MIT).
