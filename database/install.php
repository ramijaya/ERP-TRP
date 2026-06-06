<?php
require_once __DIR__ . '/../config/database.php';

try {
    // Create database
    $pdo = new PDO("mysql:host=" . DB_HOST . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE " . DB_NAME);

    // Users
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        email VARCHAR(100),
        role ENUM('admin','manager','staff') DEFAULT 'staff',
        avatar VARCHAR(255) DEFAULT NULL,
        is_active TINYINT(1) DEFAULT 1,
        last_login DATETIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // Company settings
    $pdo->exec("CREATE TABLE IF NOT EXISTS company_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_name VARCHAR(200) NOT NULL,
        address TEXT,
        phone VARCHAR(30),
        email VARCHAR(100),
        website VARCHAR(200),
        tax_id VARCHAR(50),
        logo VARCHAR(255),
        currency VARCHAR(10) DEFAULT 'IDR',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Customers
    $pdo->exec("CREATE TABLE IF NOT EXISTS customers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(20) UNIQUE NOT NULL,
        name VARCHAR(200) NOT NULL,
        company VARCHAR(200),
        email VARCHAR(100),
        phone VARCHAR(30),
        address TEXT,
        city VARCHAR(100),
        country VARCHAR(100) DEFAULT 'Indonesia',
        tax_id VARCHAR(50),
        credit_limit DECIMAL(15,2) DEFAULT 0,
        balance DECIMAL(15,2) DEFAULT 0,
        status ENUM('active','inactive') DEFAULT 'active',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // Suppliers
    $pdo->exec("CREATE TABLE IF NOT EXISTS suppliers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(20) UNIQUE NOT NULL,
        name VARCHAR(200) NOT NULL,
        company VARCHAR(200),
        email VARCHAR(100),
        phone VARCHAR(30),
        address TEXT,
        city VARCHAR(100),
        country VARCHAR(100) DEFAULT 'Indonesia',
        tax_id VARCHAR(50),
        balance DECIMAL(15,2) DEFAULT 0,
        status ENUM('active','inactive') DEFAULT 'active',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // Product categories
    $pdo->exec("CREATE TABLE IF NOT EXISTS product_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        parent_id INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (parent_id) REFERENCES product_categories(id) ON DELETE SET NULL
    )");

    // Products
    $pdo->exec("CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(30) UNIQUE NOT NULL,
        name VARCHAR(200) NOT NULL,
        category_id INT,
        description TEXT,
        unit VARCHAR(20) DEFAULT 'pcs',
        purchase_price DECIMAL(15,2) DEFAULT 0,
        selling_price DECIMAL(15,2) DEFAULT 0,
        stock INT DEFAULT 0,
        min_stock INT DEFAULT 0,
        max_stock INT DEFAULT 0,
        location VARCHAR(100),
        image VARCHAR(255),
        status ENUM('active','inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES product_categories(id) ON DELETE SET NULL
    )");

    // Sales orders
    $pdo->exec("CREATE TABLE IF NOT EXISTS sales_orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_number VARCHAR(30) UNIQUE NOT NULL,
        customer_id INT NOT NULL,
        order_date DATE NOT NULL,
        due_date DATE,
        status ENUM('draft','confirmed','processing','shipped','delivered','cancelled') DEFAULT 'draft',
        subtotal DECIMAL(15,2) DEFAULT 0,
        tax_amount DECIMAL(15,2) DEFAULT 0,
        discount_amount DECIMAL(15,2) DEFAULT 0,
        total DECIMAL(15,2) DEFAULT 0,
        paid_amount DECIMAL(15,2) DEFAULT 0,
        payment_status ENUM('unpaid','partial','paid') DEFAULT 'unpaid',
        notes TEXT,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES customers(id),
        FOREIGN KEY (created_by) REFERENCES users(id)
    )");

    // Sales order items
    $pdo->exec("CREATE TABLE IF NOT EXISTS sales_order_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sales_order_id INT NOT NULL,
        product_id INT NOT NULL,
        quantity INT NOT NULL DEFAULT 1,
        unit_price DECIMAL(15,2) NOT NULL,
        discount DECIMAL(5,2) DEFAULT 0,
        tax DECIMAL(5,2) DEFAULT 0,
        total DECIMAL(15,2) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sales_order_id) REFERENCES sales_orders(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id)
    )");

    // Purchase orders
    $pdo->exec("CREATE TABLE IF NOT EXISTS purchase_orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_number VARCHAR(30) UNIQUE NOT NULL,
        supplier_id INT NOT NULL,
        order_date DATE NOT NULL,
        expected_date DATE,
        status ENUM('draft','confirmed','ordered','received','cancelled') DEFAULT 'draft',
        subtotal DECIMAL(15,2) DEFAULT 0,
        tax_amount DECIMAL(15,2) DEFAULT 0,
        discount_amount DECIMAL(15,2) DEFAULT 0,
        total DECIMAL(15,2) DEFAULT 0,
        paid_amount DECIMAL(15,2) DEFAULT 0,
        payment_status ENUM('unpaid','partial','paid') DEFAULT 'unpaid',
        notes TEXT,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
        FOREIGN KEY (created_by) REFERENCES users(id)
    )");

    // Purchase order items
    $pdo->exec("CREATE TABLE IF NOT EXISTS purchase_order_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        purchase_order_id INT NOT NULL,
        product_id INT NOT NULL,
        quantity INT NOT NULL DEFAULT 1,
        unit_price DECIMAL(15,2) NOT NULL,
        discount DECIMAL(5,2) DEFAULT 0,
        tax DECIMAL(5,2) DEFAULT 0,
        total DECIMAL(15,2) NOT NULL,
        received_qty INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id)
    )");

    // Invoices
    $pdo->exec("CREATE TABLE IF NOT EXISTS invoices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_number VARCHAR(30) UNIQUE NOT NULL,
        type ENUM('sales','purchase') NOT NULL,
        reference_id INT,
        customer_id INT,
        supplier_id INT,
        invoice_date DATE NOT NULL,
        due_date DATE,
        subtotal DECIMAL(15,2) DEFAULT 0,
        tax_amount DECIMAL(15,2) DEFAULT 0,
        discount_amount DECIMAL(15,2) DEFAULT 0,
        total DECIMAL(15,2) DEFAULT 0,
        paid_amount DECIMAL(15,2) DEFAULT 0,
        status ENUM('draft','sent','paid','overdue','cancelled') DEFAULT 'draft',
        notes TEXT,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES customers(id),
        FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
        FOREIGN KEY (created_by) REFERENCES users(id)
    )");

    // Payments
    $pdo->exec("CREATE TABLE IF NOT EXISTS payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        payment_number VARCHAR(30) UNIQUE NOT NULL,
        invoice_id INT,
        type ENUM('incoming','outgoing') NOT NULL,
        amount DECIMAL(15,2) NOT NULL,
        payment_method ENUM('cash','bank_transfer','credit_card','check','other') DEFAULT 'cash',
        payment_date DATE NOT NULL,
        reference VARCHAR(100),
        notes TEXT,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (invoice_id) REFERENCES invoices(id),
        FOREIGN KEY (created_by) REFERENCES users(id)
    )");

    // Chart of accounts
    $pdo->exec("CREATE TABLE IF NOT EXISTS chart_of_accounts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        account_code VARCHAR(20) UNIQUE NOT NULL,
        account_name VARCHAR(200) NOT NULL,
        account_type ENUM('asset','liability','equity','revenue','expense') NOT NULL,
        parent_id INT DEFAULT NULL,
        balance DECIMAL(15,2) DEFAULT 0,
        description TEXT,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (parent_id) REFERENCES chart_of_accounts(id) ON DELETE SET NULL
    )");

    // Journal entries
    $pdo->exec("CREATE TABLE IF NOT EXISTS journal_entries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        entry_number VARCHAR(30) UNIQUE NOT NULL,
        entry_date DATE NOT NULL,
        description TEXT,
        reference VARCHAR(100),
        status ENUM('draft','posted','void') DEFAULT 'draft',
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES users(id)
    )");

    // Journal entry lines
    $pdo->exec("CREATE TABLE IF NOT EXISTS journal_entry_lines (
        id INT AUTO_INCREMENT PRIMARY KEY,
        journal_entry_id INT NOT NULL,
        account_id INT NOT NULL,
        debit DECIMAL(15,2) DEFAULT 0,
        credit DECIMAL(15,2) DEFAULT 0,
        description TEXT,
        FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE CASCADE,
        FOREIGN KEY (account_id) REFERENCES chart_of_accounts(id)
    )");

    // Employees
    $pdo->exec("CREATE TABLE IF NOT EXISTS employees (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id VARCHAR(20) UNIQUE NOT NULL,
        user_id INT,
        full_name VARCHAR(100) NOT NULL,
        email VARCHAR(100),
        phone VARCHAR(30),
        address TEXT,
        department VARCHAR(100),
        position VARCHAR(100),
        hire_date DATE,
        birth_date DATE,
        gender ENUM('male','female','other'),
        salary DECIMAL(15,2) DEFAULT 0,
        status ENUM('active','inactive','terminated') DEFAULT 'active',
        photo VARCHAR(255),
        emergency_contact VARCHAR(200),
        emergency_phone VARCHAR(30),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    )");

    // Attendance
    $pdo->exec("CREATE TABLE IF NOT EXISTS attendance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        date DATE NOT NULL,
        check_in TIME,
        check_out TIME,
        status ENUM('present','absent','late','leave','sick') DEFAULT 'present',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (employee_id) REFERENCES employees(id),
        UNIQUE KEY unique_attendance (employee_id, date)
    )");

    // Leaves
    $pdo->exec("CREATE TABLE IF NOT EXISTS leaves (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        leave_type ENUM('annual','sick','personal','maternity','other') NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        days INT NOT NULL,
        reason TEXT,
        status ENUM('pending','approved','rejected') DEFAULT 'pending',
        approved_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (employee_id) REFERENCES employees(id),
        FOREIGN KEY (approved_by) REFERENCES users(id)
    )");

    // Stock movements
    $pdo->exec("CREATE TABLE IF NOT EXISTS stock_movements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        type ENUM('in','out','adjustment') NOT NULL,
        quantity INT NOT NULL,
        reference_type VARCHAR(50),
        reference_id INT,
        notes TEXT,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id),
        FOREIGN KEY (created_by) REFERENCES users(id)
    )");

    // Activity log
    $pdo->exec("CREATE TABLE IF NOT EXISTS activity_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        action VARCHAR(100) NOT NULL,
        module VARCHAR(50),
        description TEXT,
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");

    // Insert default admin user (password: admin123)
    $hash = password_hash('admin123', PASSWORD_DEFAULT);
    $pdo->exec("INSERT IGNORE INTO users (username, password, full_name, email, role) VALUES ('admin', '$hash', 'Administrator', 'admin@erp-trp.com', 'admin')");

    // Insert default company
    $pdo->exec("INSERT IGNORE INTO company_settings (company_name, address, phone, email) VALUES ('My Company', 'Jakarta, Indonesia', '+62-21-1234567', 'info@mycompany.com')");

    // Insert default chart of accounts
    $accounts = [
        ['1000', 'Cash', 'asset'],
        ['1100', 'Bank', 'asset'],
        ['1200', 'Accounts Receivable', 'asset'],
        ['1300', 'Inventory', 'asset'],
        ['1400', 'Prepaid Expenses', 'asset'],
        ['1500', 'Fixed Assets', 'asset'],
        ['2000', 'Accounts Payable', 'liability'],
        ['2100', 'Accrued Expenses', 'liability'],
        ['2200', 'Tax Payable', 'liability'],
        ['2300', 'Short-term Loans', 'liability'],
        ['3000', 'Owner Equity', 'equity'],
        ['3100', 'Retained Earnings', 'equity'],
        ['4000', 'Sales Revenue', 'revenue'],
        ['4100', 'Service Revenue', 'revenue'],
        ['4200', 'Other Income', 'revenue'],
        ['5000', 'Cost of Goods Sold', 'expense'],
        ['5100', 'Salary Expense', 'expense'],
        ['5200', 'Rent Expense', 'expense'],
        ['5300', 'Utilities Expense', 'expense'],
        ['5400', 'Marketing Expense', 'expense'],
        ['5500', 'Office Supplies', 'expense'],
        ['5600', 'Depreciation Expense', 'expense'],
        ['5700', 'Insurance Expense', 'expense'],
        ['5800', 'Miscellaneous Expense', 'expense'],
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO chart_of_accounts (account_code, account_name, account_type) VALUES (?, ?, ?)");
    foreach ($accounts as $acc) {
        $stmt->execute($acc);
    }

    // Insert sample product categories
    $pdo->exec("INSERT IGNORE INTO product_categories (id, name, description) VALUES
        (1, 'Electronics', 'Electronic devices and accessories'),
        (2, 'Office Supplies', 'Office stationery and supplies'),
        (3, 'Furniture', 'Office and home furniture'),
        (4, 'Services', 'Service-based products')
    ");

    echo "<h2 style='color:green'>&#10004; Database installed successfully!</h2>";
    echo "<p>Default admin login:</p>";
    echo "<ul><li>Username: <b>admin</b></li><li>Password: <b>admin123</b></li></ul>";
    echo "<p><a href='" . "/ERP-TRP/" . "'>Go to ERP-TRP &rarr;</a></p>";

} catch (PDOException $e) {
    echo "<h2 style='color:red'>&#10008; Installation failed</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
