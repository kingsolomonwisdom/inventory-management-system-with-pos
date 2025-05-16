# 🗂️ Inventory Management System (IMS)

A complete, responsive inventory management system with POS functionality built with PHP and MySQL.

## 🔧 Features

- 👤 User Authentication with admin/staff roles
- 📦 Inventory Management with low stock alerts
- 🧾 Point of Sale (POS) System
- 📊 Sales and Inventory Reports
- 📥 Export data to CSV
- 📱 Responsive design for all devices
- 🔄 Real-time inventory updates

## 🛠️ Installation

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- XAMPP, WAMP, MAMP, or any PHP development environment

### Setup Instructions

1. **Clone or download the repository** to your web server's document root (e.g., `htdocs` for XAMPP).

2. **Start your web server and MySQL** service.

3. **Access the application** by navigating to:
   ```
   http://localhost/inventory-management-system
   ```

4. **Database setup**:
   - The system will automatically create the database and tables on first access
   - Default admin account:
     - Username: `admin`
     - Password: `admin`

5. **Security recommendations for production**:
   - Change the default admin password immediately
   - Update database credentials in `config/database.php`
   - Secure the `config` directory from direct web access

## 📋 Usage Guide

### Login
- Use the default admin credentials to log in:
  - Username: `admin`
  - Password: `admin`

### Dashboard
- Provides an overview of sales, inventory stats, and low stock alerts

### Inventory Management
- Add, edit, delete products
- Upload product images
- Set low stock thresholds
- View inventory status

### Categories
- Organize products into categories
- Manage category names

### Point of Sale (POS)
- Add products to cart
- Process sales
- Generate receipts
- Update inventory automatically

### Sales
- View sales history
- Filter by date and staff
- View detailed sale information
- Print receipts for previous sales

### Reports
- Generate inventory reports
- View sales analytics
- Export data to CSV

### User Management
- Create/manage user accounts
- Set user roles (admin/staff)
- Update profile and password

## 📱 Responsive Design

This application has been designed to work on all devices:
- Desktop computers
- Tablets
- Mobile phones

The interface automatically adjusts based on screen size to provide optimal user experience.

## 🔒 Security Features

- Password hashing
- Prepared SQL statements to prevent SQL injection
- Input sanitation
- Role-based access control

## 📷 Screenshots

Screenshots can be found in the `docs/screenshots` directory.

## 📝 License

This project is licensed under the MIT License - see the LICENSE file for details.

---

## 🚀 Development

To modify or extend this application:

1. Understand the folder structure:
   ```
   inventory-system/
   ├── config/               # DB connection, config files
   ├── assets/               # CSS, JS, images
   ├── includes/             # Reusable PHP includes
   ├── pages/                # Dashboard, POS, Inventory, etc.
   ├── uploads/              # Product images
   ├── exports/              # Excel reports
   ├── index.php             # Entry point
   └── login.php             # Authentication
   ```

2. Make your modifications

3. Test thoroughly before deploying to production 