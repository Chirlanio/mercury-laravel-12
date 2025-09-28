# Mercury Laravel

Mercury Laravel is a web application built with the Laravel framework and a React frontend using Inertia.js. It provides a starting point for applications requiring user authentication, user management, activity logging, and more.

## Key Features

*   **User Authentication**: Secure login, registration, and password reset functionality.
*   **User Management**: Easily create, read, update, and delete users.
*   **Profile Management**: Users can update their profile information and upload an avatar.
*   **Activity Logging**: Track user actions within the application.
*   **Dashboard**: A central hub displaying application statistics and recent activities.
*   **Role-Based Access Control (RBAC)**: Control user access to features based on roles and permissions.
*   **Modern Tech Stack**: Built with the latest versions of Laravel, React, and Vite.

## Technologies Used

### Backend

*   [Laravel](https://laravel.com/)
*   [Inertia.js (Server-side)](https://inertiajs.com/)
*   [Intervention Image](https://image.intervention.io/)
*   [Laravel Sanctum](https://laravel.com/docs/sanctum)
*   [Ziggy](https://github.com/tightenco/ziggy)

### Frontend

*   [React](https://reactjs.org/)
*   [Inertia.js (Client-side)](https://inertiajs.com/)
*   [Tailwind CSS](https://tailwindcss.com/)
*   [Vite](https://vitejs.dev/)
*   [Axios](https://axios-http.com/)
*   [React Toastify](https://fkhadra.github.io/react-toastify/introduction)
*   [Heroicons](https://heroicons.com/)

## Getting Started

These instructions will get you a copy of the project up and running on your local machine for development and testing purposes.

### Prerequisites

*   PHP >= 8.2
*   Node.js and npm
*   Composer
*   A database (e.g., MySQL, PostgreSQL, SQLite)

### Installation

1.  **Clone the repository**

    ```bash
    git clone https://github.com/your-username/mercury-laravel.git
    cd mercury-laravel
    ```

2.  **Install backend dependencies**

    ```bash
    composer install
    ```

3.  **Install frontend dependencies**

    ```bash
    npm install
    ```

4.  **Set up your environment**

    *   Copy the `.env.example` file to `.env`:

        ```bash
        cp .env.example .env
        ```

    *   Generate an application key:

        ```bash
        php artisan key:generate
        ```

    *   Configure your database connection in the `.env` file.

5.  **Run database migrations and seeders**

    ```bash
    php artisan migrate --seed
    ```

6.  **Build frontend assets**

    ```bash
    npm run build
    ```

## Usage

To start the development server, you can use the following command:

```bash
composer run dev
```

This will concurrently start the PHP development server, the Vite development server, and the queue listener.

You can then access the application in your browser at the address provided by the `php artisan serve` command (usually `http://127.0.0.1:8000`).

### Running Tests

To run the application's test suite, use the following command:

```bash
composer run test
```

## Contributing

Contributions are what make the open source community such an amazing place to learn, inspire, and create. Any contributions you make are **greatly appreciated**.

If you have a suggestion that would make this better, please fork the repo and create a pull request. You can also simply open an issue with the tag "enhancement".

1.  Fork the Project
2.  Create your Feature Branch (`git checkout -b feature/AmazingFeature`)
3.  Commit your Changes (`git commit -m 'Add some AmazingFeature'`)
4.  Push to the Branch (`git push origin feature/AmazingFeature`)
5.  Open a Pull Request

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).