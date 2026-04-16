# Campus Marketplace

A modern, scalable, full-stack marketplace platform built for university students to buy, sell, and trade safely.

## Features

- **Dynamic Dashboards**: Role-based routing for Admin, Sellers, and Buyers with clickable analytics cards.
- **Glassmorphism UI**: Premium, responsive, dark-mode CSS aesthetics.
- **Wallet & Fintech System**: Internal point/currency system with deposit and transaction receipts.
- **Real-Time Features**: AJAX polling for chat capability.
- **AI-Ready Modules**: Mock API endpoints for dynamic description generation and image flyer generation.
- **Security & Moderation**: PDO Prepared statements, admin approval workflows, audit logging, and Vacation mode.

## Tech Stack

- **Frontend**: HTML5, Vanilla JavaScript, CSS3 (Glassmorphism design system)
- **Backend**: Procedural PHP 8+
- **Database**: MySQL (InnoDB)

## Folder Structure

```
marketplace/
│
├── api/
│   └── chat.php              # Real-time chat & username availability endpoints
├── admin/
│   ├── index.php             # Admin Dashboard (Stats, top sellers, clickables)
│   ├── users.php             # User Management (Verify, suspend, upgrade)
│   ├── products.php          # Moderation Desk (Approve/Reject products)
│   ├── messages.php          # Omni Chat View
│   ├── audit.php             # Platform Audit Log
│   ├── settings.php          # Maintenance mode & Database backup
│   ├── header.php            # Admin partial
│   └── footer.php            # Admin partial
├── assets/
│   ├── css/
│   │   └── style.css         # Core CSS design system
│   └── js/
│       └── main.js           # AJAX handlers, password strength, polling
├── includes/
│   ├── db.php                # Core logic: PDO connection, auth checks, helper functions
│   ├── header.php            # Main site header (Maintenance check logic)
│   └── footer.php            # Main site footer
├── uploads/                  # User generated content
│   ├── avatars/              
│   ├── banners/              
│   └── products/             
├── index.php                 # Homepage (Dynamic product grid with filters)
├── login.php                 # Authentication login
├── register.php              # Dual-mode (Buyer/Seller) registration
├── dashboard.php             # Seller/Buyer Dashboard (Clickable metrics, tools)
├── product.php               # Detailed product view (Reviews, gallery, Buy action)
├── add_product.php           # AI integration & multiple image upload
├── edit_profile.php          # Profile management
├── deposit.php               # Wallet deposit simulation
├── chat.php                  # User-to-user messaging UI
├── generate_flyer.php        # Auto-generates social media promo images
├── receipt.php               # Digital receipt generator
└── setup.php                 # Database schema initialization
```

## Setup Instructions

1. **Environment Requirements**: 
   - Ensure you are running a local server like **XAMPP**, **MAMP**, or **WAMP**.
   - Make sure Apache and MySQL are running.
   - PHP versions 8.0+ are highly recommended.

2. **Installation**:
   - Clone or copy this entire folder into your local `htdocs` or `www` directory (name the folder `marketplace`).
   
3. **Database Initialization**:
   - Open your browser and navigate to `http://localhost/marketplace/setup.php`.
   - Wait for the setup script to drop existing tables and recreate the entire schema perfectly.
   - Using this script automatically registers a default admin user.

4. **Running Locally**:
   - Navigate to the homepage at `http://localhost/marketplace/index.php`.
   - **Admin Login:** Use `admin@campus.com` / `admin123`.

## Database Schema (High-Level)
* **users**: Manages identities, balances, roles (buyer/seller/admin), and tier management (basic/premium).
* **products**: Tracks product states, stock, pricing, and moderation states (pending/approved/paused).
* **transactions**: ACID compliant financial tracking for purchases, boosts, and referrals.
* **messages**: Inter-user messaging with read receipts.
* **reviews**: Products feedback tracking.
* **audit_log**: Administrator oversight history.
