# ABS - Web & Mobile Application

A complete business solution with web and mobile applications.

## 📁 Project Structure

ABS/
├── web/ # Laravel Web Application
├── mobile/ # Flutter Mobile Application
└── README.md



## 🚀 Getting Started

### Web Application Setup

```bash
cd web
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

**Access:** http://localhost:8000

### Mobile Application Setup

```bash
cd mobile
flutter pub get
flutter run
```

## 📝 Commit Guidelines

- **Web changes:** `git commit -m "feat(web): description"`
- **Mobile changes:** `git commit -m "feat(mobile): description"`
- **Both:** `git commit -m "docs: description"`

## 👨‍💻 Author

Arman Sabir
Email: i@armansabir.com

## 📄 License

This project is private and confidential.