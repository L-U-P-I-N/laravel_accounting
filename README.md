# Laravel Accounting System

A comprehensive accounting system migrated from Flask to Laravel, designed for small and medium businesses with multi-company support, multi-currency capabilities, and tax compliance for various countries.

## Features

### Core Accounting
- **Chart of Accounts**: Professional hierarchical account structure with automatic balance tracking
- **Journal Entries**: Manual and automatic journal entry creation with posting capabilities
- **Double-Entry Bookkeeping**: Ensures balanced debits and credits for all transactions

### Invoicing & Sales
- **Invoice Management**: Create, send, and track professional invoices
- **Multiple Currencies**: Support for SAR, USD, AED, EGP, JOD and more
- **Payment Tracking**: Monitor invoice payments and outstanding balances
- **Automated Reminders**: Send payment reminders to customers

### Purchasing & Expenses
- **Purchase Orders**: Manage supplier purchases and expenses
- **Expense Tracking**: Categorize and monitor business expenses
- **Supplier Management**: Maintain supplier information and balances

### Payroll & HR
- **Employee Management**: Complete employee records with salary details
- **Payroll Processing**: Calculate salaries, taxes, and deductions
- **GOSI Integration**: Saudi Arabian social insurance calculations
- **Payslip Generation**: Automatic payslip creation

### Tax Compliance
- **VAT Calculation**: Automatic VAT calculation for Saudi Arabia (15%), UAE (5%), Egypt (14%), Jordan (16%)
- **Tax Reports**: Generate tax returns and compliance reports
- **ZATCA Support**: Saudi Arabian tax authority integration ready

### Reporting
- **Financial Statements**: Balance Sheet, Income Statement, Cash Flow
- **Trial Balance**: Detailed account balance reports
- **Custom Reports**: Flexible reporting system
- **Dashboard Analytics**: Real-time business insights

### Multi-Tenancy
- **Multi-Company**: Support for multiple companies per user
- **Role-Based Access**: Admin, Accountant, User, and Viewer roles
- **Data Isolation**: Complete separation of company data

## Installation

### Prerequisites
- PHP 8.2 or higher
- Composer
- MySQL database
- Node.js & npm (for asset compilation)

### Setup Steps

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd laravel_accounting
   ```

2. **Install dependencies**
   ```bash
   composer install
   npm install
   ```

3. **Environment configuration**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Configure database**
   Edit `.env` file for MySQL:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=accounting
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   ```

5. **Run migrations**
   ```bash
   php artisan migrate
   ```

6. **Create storage link**
   ```bash
   php artisan storage:link
   ```

7. **Compile assets**
   ```bash
   npm run build
   ```

8. **Start the development server**
   ```bash
   php artisan serve
   ```

## Usage

### Getting Started

1. **Register a new account**: Visit `/register` to create your company account
2. **Complete company setup**: Provide company details and select your country
3. **Chart of accounts**: Default chart of accounts is automatically created
4. **Start using**: Begin creating invoices, managing expenses, and running reports

### Key Workflows

#### Creating an Invoice
1. Navigate to Invoices → Create Invoice
2. Select customer and invoice details
3. Add line items with quantities and prices
4. Review automatic tax calculations
5. Send invoice to customer

#### Recording Journal Entry
1. Navigate to Accounting → Journal Entries
2. Click "Add Journal Entry"
3. Enter entry date and description
4. Add debit and credit lines ensuring balance
5. Post the entry to update account balances

#### Processing Payroll
1. Navigate to HR → Payroll
2. Select pay period and employees
3. Review calculated salaries and deductions
4. Approve and process payroll
5. Generate payslips for employees

## Migration from Flask

This application was successfully migrated from Flask to Laravel with the following improvements:

### Database Layer
- **From SQLAlchemy to Eloquent**: More PHP-native and Laravel-integrated ORM
- **Migration System**: Laravel's robust migration system for database versioning
- **Relationships**: Cleaner relationship definitions using Eloquent methods

### Authentication & Security
- **Laravel Sanctum**: Modern API authentication system
- **Middleware**: Laravel's middleware for route protection
- **Validation**: Built-in request validation system

### Frontend
- **Blade Templates**: More powerful and secure templating engine
- **Component Structure**: Reusable view components
- **Asset Pipeline**: Vite for modern asset compilation

### Architecture
- **MVC Pattern**: Proper separation of concerns
- **Service Container**: Dependency injection and service providers
- **Queue System**: Background job processing

## License

This project is licensed under the MIT License.

---

**Note**: This is a complete accounting system suitable for production use. Always ensure proper backups and security measures are in place when handling financial data.

## InfinityFree Deployment

InfinityFree uses Apache with MySQL, and the document root is typically `htdocs`. Use the guide in [INFINITYFREE_DEPLOYMENT.md](INFINITYFREE_DEPLOYMENT.md) to prepare the upload structure and database credentials.

Recommended production values:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.infinityfreeapp.com
DB_CONNECTION=mysql
DB_HOST=sqlXXX.infinityfree.com
DB_PORT=3306
DB_DATABASE=if0_12345678_accounting
DB_USERNAME=if0_12345678
DB_PASSWORD=your_mysql_password
```

For InfinityFree, upload the full Laravel app outside `htdocs`, upload the prepared `infinityfree/htdocs` files into `htdocs`, and make sure `storage` and `bootstrap/cache` remain writable.

## Railway Deployment

For Railway production deployment with demo data loaded into MySQL on the first deploy, use the guide in [RAILWAY_DEPLOYMENT.md](RAILWAY_DEPLOYMENT.md).

The project includes a safe one-time bootstrap command:

```bash
php artisan app:seed-demo-if-empty
```

This command seeds demo data only when the database is empty, then rebuilds accounting entries, payments, and inventory movements.
