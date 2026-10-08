SET NAMES utf8mb4;

DELETE FROM uploads;
DELETE FROM payments;
DELETE FROM quote_items;
DELETE FROM quotes;
DELETE FROM invoice_items;
DELETE FROM invoices;
DELETE FROM payment_modes;
DELETE FROM taxes;
DELETE FROM clients;
DELETE FROM settings;
DELETE FROM password_resets;
DELETE FROM admins;

ALTER TABLE admins AUTO_INCREMENT = 1;
ALTER TABLE clients AUTO_INCREMENT = 1;
ALTER TABLE taxes AUTO_INCREMENT = 1;
ALTER TABLE payment_modes AUTO_INCREMENT = 1;
ALTER TABLE invoices AUTO_INCREMENT = 1;
ALTER TABLE invoice_items AUTO_INCREMENT = 1;
ALTER TABLE quotes AUTO_INCREMENT = 1;
ALTER TABLE quote_items AUTO_INCREMENT = 1;
ALTER TABLE payments AUTO_INCREMENT = 1;
ALTER TABLE settings AUTO_INCREMENT = 1;
ALTER TABLE uploads AUTO_INCREMENT = 1;

INSERT INTO admins (id, name, surname, email, password_hash, photo, role, enabled, removed) VALUES
(1, 'Admin', 'User', 'admin@admin.com', '$2y$12$H1UK/mvvYSsdPZ4F2V5bq.exgIbudZAzJlLut.HnnlsNcG3N5VnV2', NULL, 'admin', 1, 0);

INSERT INTO settings (setting_category, setting_key, setting_value) VALUES
('money_format_settings', 'default_currency_code', 'PKR'),
('money_format_settings', 'currency_code', 'PKR'),
('money_format_settings', 'currency_name', 'Pakistani Rupee'),
('money_format_settings', 'currency_symbol', 'Rs'),
('money_format_settings', 'currency_position', 'before'),
('money_format_settings', 'decimal_sep', '.'),
('money_format_settings', 'thousand_sep', ','),
('money_format_settings', 'cent_precision', '2'),
('money_format_settings', 'zero_format', 'false'),
('company_settings', 'company_name', 'Ledger Preview Co.'),
('company_settings', 'company_address', '42 Demo Avenue, Suite 100'),
('company_settings', 'company_state', ''),
('company_settings', 'company_country', ''),
('company_settings', 'company_email', 'hello@ledgerpreview.example'),
('company_settings', 'company_phone', '+1 555 0100'),
('company_settings', 'company_website', ''),
('company_settings', 'company_tax_number', ''),
('company_settings', 'company_vat_number', ''),
('company_settings', 'company_reg_number', ''),
('company_settings', 'company_logo', ''),
('app_settings', 'idurar_app_date_format', 'DD/MM/YYYY'),
('app_settings', 'idurar_app_company_email', 'hello@ledgerpreview.example'),
('finance_settings', 'last_invoice_number', '1004'),
('finance_settings', 'last_quote_number', '503'),
('finance_settings', 'last_payment_number', '2003');

INSERT INTO clients (id, name, country, address, phone, email, removed, created_at) VALUES
(1, 'Acme Trading Co.', 'United States', '120 Market Street, San Francisco, CA', '+1 415 555 0142', 'billing@acmetrading.example', 0, '2026-01-10 10:00:00'),
(2, 'Northwind Retail', 'Canada', '88 King Street West, Toronto, ON', '+1 416 555 0198', 'accounts@northwind.example', 0, '2026-02-05 10:00:00'),
(3, 'Bright Labs GmbH', 'Germany', 'Friedrichstrasse 50, Berlin', '+49 30 555 2211', 'finance@brightlabs.example', 0, '2026-03-01 10:00:00'),
(4, 'Sahara Supplies', 'United Arab Emirates', 'Business Bay, Dubai', '+971 4 555 9080', 'orders@saharasupplies.example', 0, '2025-12-15 10:00:00');

INSERT INTO taxes (id, tax_name, tax_value, is_default, enabled, removed) VALUES
(1, 'VAT 10%', 10.00, 1, 1, 0),
(2, 'Sales Tax 5%', 5.00, 0, 1, 0),
(3, 'Zero Tax', 0.00, 0, 1, 0);

INSERT INTO payment_modes (id, name, description, is_default, enabled, removed) VALUES
(1, 'Bank Transfer', 'Direct bank wire', 1, 1, 0),
(2, 'Credit Card', 'Visa / Mastercard', 0, 1, 0),
(3, 'Cash', 'Cash payment', 0, 1, 0);

INSERT INTO invoices (id, client_id, number, year, date, expired_date, currency, status, payment_status, notes, sub_total, tax_rate, tax_total, total, discount, credit, removed) VALUES
(1, 1, 1001, 2026, '2026-03-01', '2026-03-31', 'PKR', 'pending', 'partially', NULL, 1200.00, 10.00, 120.00, 1320.00, 0.00, 500.00, 0),
(2, 2, 1002, 2026, '2026-03-10', '2026-04-10', 'PKR', 'sent', 'partially', NULL, 2500.00, 10.00, 250.00, 2750.00, 0.00, 1000.00, 0),
(3, 3, 1003, 2026, '2026-02-15', '2026-03-15', 'PKR', 'paid', 'paid', NULL, 900.00, 5.00, 45.00, 945.00, 0.00, 945.00, 0),
(4, 4, 1004, 2026, '2026-01-20', '2026-02-20', 'PKR', 'overdue', 'unpaid', NULL, 1800.00, 10.00, 180.00, 1980.00, 0.00, 0.00, 0);

INSERT INTO invoice_items (invoice_id, item_name, description, price, quantity, total) VALUES
(1, 'Website Redesign', 'Landing page and branding refresh', 800.00, 1.00, 800.00),
(1, 'Hosting Setup', 'Annual hosting configuration', 200.00, 2.00, 400.00),
(2, 'Inventory System', 'Module license', 2500.00, 1.00, 2500.00),
(3, 'Support Retainer', 'February support hours', 90.00, 10.00, 900.00),
(4, 'Warehouse Audit', 'On-site inventory audit', 1800.00, 1.00, 1800.00);

INSERT INTO quotes (id, client_id, number, year, date, expired_date, currency, status, notes, sub_total, tax_rate, tax_total, total, discount, removed) VALUES
(1, 1, 501, 2026, '2026-03-05', '2026-04-05', 'PKR', 'draft', NULL, 1500.00, 10.00, 150.00, 1650.00, 0.00, 0),
(2, 2, 502, 2026, '2026-03-12', '2026-04-12', 'PKR', 'sent', NULL, 3200.00, 10.00, 320.00, 3520.00, 0.00, 0),
(3, 3, 503, 2026, '2026-02-28', '2026-03-28', 'PKR', 'accepted', NULL, 750.00, 5.00, 37.50, 787.50, 0.00, 0);

INSERT INTO quote_items (quote_id, item_name, description, price, quantity, total) VALUES
(1, 'CRM Onboarding', 'Setup and training package', 1500.00, 1.00, 1500.00),
(2, 'Custom Reports', 'Dashboard and export suite', 1600.00, 2.00, 3200.00),
(3, 'Email Templates', 'Invoice and quote templates', 250.00, 3.00, 750.00);

INSERT INTO payments (id, number, year, client_id, invoice_id, payment_mode_id, date, amount, currency, ref, description, removed) VALUES
(1, 2001, 2026, 2, 2, 1, '2026-03-11', 1000.00, 'PKR', NULL, NULL, 0),
(2, 2002, 2026, 3, 3, 2, '2026-02-16', 945.00, 'PKR', NULL, NULL, 0),
(3, 2003, 2026, 1, 1, 3, '2026-03-18', 500.00, 'PKR', NULL, NULL, 0);
