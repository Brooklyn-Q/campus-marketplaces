# 🛒 Campus Marketplace

A modern, scalable, full-stack marketplace platform built for university students to buy, sell, and trade safely. This version has been fully refactored into a **React (Vite) + Tailwind CSS** frontend and a **RESTful PHP** backend.

## ✨ Features

- **Dynamic Dashboards**: Role-based routing for Admin, Sellers, and Buyers with real-time analytics.
- **Modern UI/UX**: Premium Glassmorphism design system with full Dark Mode support.
- **Fintech Integration**: Internal wallet system with Paystack integration for secure payments.
- **Real-Time Communication**: Integrated chat system for buyers and sellers.
- **AI-Powered**: Ready for Gemini AI integrations for product descriptions and automated social media flyers.
- **Production Ready**: Configured for deployment on **Netlify** (Frontend) and **Render** (Backend).

## 🏗️ Architecture

### Frontend (React SPA)
- **Vite**: Ultra-fast development and build tool.
- **Tailwind CSS**: Utility-first styling with custom glassmorphism components.
- **React Router**: Client-side routing for seamless transitions.
- **Context API**: Global state management for Auth and Theme.

### Backend (PHP REST API)
- **RESTful Routes**: Clean API endpoints for all marketplace actions.
- **PDO Security**: Fully prepared statements to prevent SQL injection.
- **JWT Auth**: Secure, token-based authentication.
- **Cloudinary**: Cloud-based image management for high-performance asset delivery.

## 📂 Folder Structure

```
marketplace/
├── frontend/             # React (Vite) Single Page Application
│   ├── src/              # Application logic, components, and hooks
│   ├── public/           # Static assets and legacy CSS
│   └── netlify.toml      # Netlify deployment configuration
├── backend/              # PHP REST API
│   ├── config/           # Database, JWT, and Cloudinary configuration
│   ├── routes/           # REST API endpoint handlers
│   ├── models/           # Data access logic
│   └── render.yaml       # Render deployment configuration
└── .gitignore            # Root git ignore
```

## 🚀 Setup & Local Development

### 1. Backend Setup (XAMPP/PHP)
1. Ensure PHP 8.1+ and MySQL are running via XAMPP/MAMP.
2. Navigate to `backend/` and copy `.env.example` to `.env`.
3. Configure your database credentials.
4. Run `http://localhost/marketplace/backend/setup.php` to initialize the database.

### 2. Frontend Setup (React)
1. Navigate to the `frontend/` directory.
2. Copy `.env.example` to `.env`.
3. Install dependencies:
   ```bash
   npm install
   ```
4. Start the development server:
   ```bash
   npm run dev
   ```

## 🌍 Production Deployment

### Frontend (Netlify)
- **Build Command**: `npm run build`
- **Publish Directory**: `frontend/dist`
- **Env Variable**: Set `VITE_API_URL` to `https://campus-marketplace-api-x55w.onrender.com/api`

### Backend (Render)
- **Live URL**: `https://campus-marketplace-api-x55w.onrender.com`
- **Build Command**: `composer install` (if using composer) or skip.
- **Runtime**: PHP 8.1+
- **Env Variables**: Ensure `JWT_SECRET`, `CLOUDINARY_*`, and `PAYSTACK_*` are set.

## 🛠️ Credits & License
Built for students, by students. Free to fork and improve.
