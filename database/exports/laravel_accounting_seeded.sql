-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: laravel_accounting
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `laravel_accounting`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `laravel_accounting` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;

USE `laravel_accounting`;

--
-- Table structure for table `accounts`
--

DROP TABLE IF EXISTS `accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `accounts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `name` varchar(200) NOT NULL,
  `name_ar` varchar(200) DEFAULT NULL,
  `account_type` varchar(30) NOT NULL,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `company_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `accounts_parent_id_foreign` (`parent_id`),
  KEY `accounts_company_id_code_index` (`company_id`,`code`),
  KEY `accounts_company_id_account_type_index` (`company_id`,`account_type`),
  CONSTRAINT `accounts_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `accounts_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `accounts`
--

LOCK TABLES `accounts` WRITE;
/*!40000 ALTER TABLE `accounts` DISABLE KEYS */;
INSERT INTO `accounts` VALUES (1,'1000','النقدية والبنوك',NULL,'asset',NULL,1,0,NULL,0.00,1,'2026-03-26 07:53:33','2026-03-26 07:53:33'),(2,'1010','النقدية في الصندوق',NULL,'asset',NULL,1,0,NULL,0.00,1,'2026-03-26 07:53:33','2026-03-26 07:53:33'),(3,'1020','الحسابات الجارية البنكية',NULL,'asset',NULL,1,0,NULL,0.00,1,'2026-03-26 07:53:33','2026-03-26 07:53:33'),(4,'1100','حسابات المدينين',NULL,'asset',NULL,1,0,NULL,0.00,1,'2026-03-26 07:53:33','2026-03-26 07:53:33'),(5,'1200','المخزون',NULL,'asset',NULL,1,0,NULL,0.00,1,'2026-03-26 07:53:33','2026-03-26 07:53:33'),(6,'1300','الأصول الثابتة',NULL,'asset',NULL,1,0,NULL,0.00,1,'2026-03-26 07:53:33','2026-03-26 07:53:33'),(7,'1310','معدات ومهمات',NULL,'asset',NULL,1,0,NULL,0.00,1,'2026-03-26 07:53:33','2026-03-26 07:53:33'),(8,'1320','أثاث ومعدات مكتبية',NULL,'asset',NULL,1,0,NULL,0.00,1,'2026-03-26 07:53:33','2026-03-26 07:53:33'),(9,'2000','حسابات الدائنين',NULL,'liability',NULL,1,0,NULL,0.00,1,'2026-03-26 07:53:33','2026-03-26 07:53:33'),(10,'2100','القروض والسلف',NULL,'liability',NULL,1,0,NULL,0.00,1,'2026-03-26 07:53:33','2026-03-26 07:53:33'),(11,'2200','ضريبة القيمة المضافة المستحقة',NULL,'liability',NULL,1,0,NULL,0.00,1,'2026-03-26 07:53:33','2026-03-26 07:53:33'),(12,'3000','رأس المال',NULL,'equity',NULL,1,0,NULL,0.00,1,'2026-03-26 07:53:33','2026-03-26 07:53:33'),(13,'3100','الأرباح المحتجزة',NULL,'equity',NULL,1,0,NULL,0.00,1,'2026-03-26 07:53:33','2026-03-26 07:53:33'),(14,'4000','الإيرادات التشغيلية',NULL,'revenue',NULL,1,0,NULL,0.00,1,'2026-03-26 07:53:33','2026-03-26 07:53:33'),(15,'4100','المبيعات',NULL,'revenue',NULL,1,0,NULL,0.00,1,'2026-03-26 07:53:33','2026-03-26 07:53:33'),(16,'4200','مبيعات الخدمات',NULL,'revenue',NULL,1,0,NULL,0.00,1,'2026-03-26 07:53:33','2026-03-26 07:53:33'),(17,'4300','إيرادات أخرى',NULL,'revenue',NULL,1,0,NULL,0.00,1,'2026-03-26 07:53:33','2026-03-26 07:53:33'),(18,'5000','المصاريف التشغيلية',NULL,'expense',NULL,1,0,NULL,0.00,1,'2026-03-26 07:53:33','2026-03-26 07:53:33'),(19,'5100','تكلفة المبيعات',NULL,'expense',NULL,1,0,NULL,0.00,1,'2026-03-26 07:53:33','2026-03-26 07:53:33'),(20,'5200','الإيجار',NULL,'expense',NULL,1,0,NULL,0.00,1,'2026-03-26 07:53:33','2026-03-26 07:53:33'),(21,'5300','الرواتب والأجور',NULL,'expense',NULL,1,0,NULL,0.00,1,'2026-03-26 07:53:33','2026-03-26 07:53:33'),(22,'5400','المرافق والخدمات',NULL,'expense',NULL,1,0,NULL,0.00,1,'2026-03-26 07:53:33','2026-03-26 07:53:33'),(23,'5500','التسويق والإعلان',NULL,'expense',NULL,1,0,NULL,0.00,1,'2026-03-26 07:53:33','2026-03-26 07:53:33'),(24,'5600','مصاريف إدارية وعامة',NULL,'expense',NULL,1,0,NULL,0.00,1,'2026-03-26 07:53:33','2026-03-26 07:53:33');
/*!40000 ALTER TABLE `accounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache` (
  `key` varchar(191) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache_locks` (
  `key` varchar(191) NOT NULL,
  `owner` varchar(191) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `companies`
--

DROP TABLE IF EXISTS `companies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `companies` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `name_ar` varchar(200) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `country_code` varchar(5) NOT NULL DEFAULT 'SA',
  `currency` varchar(5) NOT NULL DEFAULT 'SAR',
  `tax_number` varchar(50) DEFAULT NULL,
  `commercial_reg` varchar(50) DEFAULT NULL,
  `logo_url` varchar(500) DEFAULT NULL,
  `fiscal_year_start` varchar(5) NOT NULL DEFAULT '01-01',
  `subscription_plan` varchar(20) NOT NULL DEFAULT 'basic',
  `subscription_status` varchar(20) NOT NULL DEFAULT 'trial',
  `subscription_start` datetime DEFAULT NULL,
  `subscription_end` datetime DEFAULT NULL,
  `stripe_customer_id` varchar(100) DEFAULT NULL,
  `stripe_subscription_id` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `companies`
--

LOCK TABLES `companies` WRITE;
/*!40000 ALTER TABLE `companies` DISABLE KEYS */;
INSERT INTO `companies` VALUES (1,'شركة التقنية المتقدمة',NULL,'info@advanced-tech.com','+966501234567','الرياض، المملكة العربية السعودية','الرياض','SA','SAR','300123456700003',NULL,NULL,'01-01','professional','active','2026-03-26 10:53:32','2026-04-26 10:53:32',NULL,NULL,'2026-03-26 07:53:32','2026-03-26 07:53:32');
/*!40000 ALTER TABLE `companies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customers`
--

DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `name_ar` varchar(200) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `tax_number` varchar(50) DEFAULT NULL,
  `credit_limit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `company_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `customers_company_id_email_unique` (`company_id`,`email`),
  UNIQUE KEY `customers_company_id_code_unique` (`company_id`,`code`),
  KEY `customers_company_id_is_active_index` (`company_id`,`is_active`),
  CONSTRAINT `customers_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customers`
--

LOCK TABLES `customers` WRITE;
/*!40000 ALTER TABLE `customers` DISABLE KEYS */;
INSERT INTO `customers` VALUES (1,NULL,'شركة الأمل للتجارة',NULL,'info@amal.com','+966501234570',NULL,'الرياض، المملكة العربية السعودية','الرياض','SA',NULL,0.00,0.00,1,1,'2026-03-26 07:53:33','2026-03-26 07:53:33'),(2,NULL,'مؤسسة النور',NULL,'info@noor.com','+966501234571',NULL,'الرياض، المملكة العربية السعودية','الرياض','SA',NULL,0.00,0.00,1,1,'2026-03-26 07:53:33','2026-03-26 07:53:33'),(3,NULL,'شركة الرائد',NULL,'info@raed.com','+966501234572',NULL,'الرياض، المملكة العربية السعودية','الرياض','SA',NULL,0.00,0.00,1,1,'2026-03-26 07:53:33','2026-03-26 07:53:33');
/*!40000 ALTER TABLE `customers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employees`
--

DROP TABLE IF EXISTS `employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employees` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `employee_number` varchar(50) NOT NULL,
  `company_id` bigint(20) unsigned NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(120) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` varchar(191) DEFAULT NULL,
  `hire_date` date NOT NULL,
  `termination_date` date DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `salary` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `employment_type` varchar(20) NOT NULL DEFAULT 'full_time',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `employees_company_id_employee_number_index` (`company_id`,`employee_number`),
  KEY `employees_company_id_status_index` (`company_id`,`status`),
  CONSTRAINT `employees_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employees`
--

LOCK TABLES `employees` WRITE;
/*!40000 ALTER TABLE `employees` DISABLE KEYS */;
INSERT INTO `employees` VALUES (1,'EMP-0001',1,'محمد','الأحمد','محمد.الأحمد@company.com','+966501234601','الرياض، المملكة العربية السعودية','2025-04-26',NULL,'مدير مالي','المالية',15000.00,'active','full_time',NULL,'2026-03-26 07:53:33','2026-03-26 07:53:33'),(2,'EMP-0002',1,'فاطمة','العلي','فاطمة.العلي@company.com','+966501234602','الرياض، المملكة العربية السعودية','2025-04-26',NULL,'محاسب','المالية',8000.00,'active','full_time',NULL,'2026-03-26 07:53:33','2026-03-26 07:53:33'),(3,'EMP-0003',1,'علي','المحمد','علي.المحمد@company.com','+966501234603','الرياض، المملكة العربية السعودية','2026-01-26',NULL,'مدير موارد بشرية','الموارد البشرية',12000.00,'active','full_time',NULL,'2026-03-26 07:53:33','2026-03-26 07:53:33');
/*!40000 ALTER TABLE `employees` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `expenses`
--

DROP TABLE IF EXISTS `expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `expenses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `expense_number` varchar(30) NOT NULL,
  `company_id` bigint(20) unsigned NOT NULL,
  `expense_account_id` bigint(20) unsigned NOT NULL,
  `payment_account_id` bigint(20) unsigned NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `expense_date` date NOT NULL,
  `name` varchar(200) NOT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax_rate` decimal(8,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` varchar(20) NOT NULL DEFAULT 'posted',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `expenses_company_id_expense_number_unique` (`company_id`,`expense_number`),
  KEY `expenses_expense_account_id_foreign` (`expense_account_id`),
  KEY `expenses_payment_account_id_foreign` (`payment_account_id`),
  KEY `expenses_created_by_foreign` (`created_by`),
  KEY `expenses_company_id_expense_date_index` (`company_id`,`expense_date`),
  CONSTRAINT `expenses_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `expenses_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `expenses_expense_account_id_foreign` FOREIGN KEY (`expense_account_id`) REFERENCES `accounts` (`id`),
  CONSTRAINT `expenses_payment_account_id_foreign` FOREIGN KEY (`payment_account_id`) REFERENCES `accounts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `expenses`
--

LOCK TABLES `expenses` WRITE;
/*!40000 ALTER TABLE `expenses` DISABLE KEYS */;
/*!40000 ALTER TABLE `expenses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(191) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoice_items`
--

DROP TABLE IF EXISTS `invoice_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invoice_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned DEFAULT NULL,
  `description` varchar(191) NOT NULL,
  `quantity` decimal(15,2) NOT NULL DEFAULT 1.00,
  `unit_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax_rate` decimal(8,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `invoice_items_product_id_foreign` (`product_id`),
  KEY `invoice_items_invoice_id_product_id_index` (`invoice_id`,`product_id`),
  CONSTRAINT `invoice_items_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `invoice_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoice_items`
--

LOCK TABLES `invoice_items` WRITE;
/*!40000 ALTER TABLE `invoice_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `invoice_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoices`
--

DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invoices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(50) NOT NULL,
  `customer_id` bigint(20) unsigned NOT NULL,
  `company_id` bigint(20) unsigned NOT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `balance_due` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `payment_status` varchar(20) NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `terms` text DEFAULT NULL,
  `currency` varchar(5) NOT NULL DEFAULT 'SAR',
  `exchange_rate` decimal(10,4) NOT NULL DEFAULT 1.0000,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `invoices_customer_id_foreign` (`customer_id`),
  KEY `invoices_company_id_invoice_number_index` (`company_id`,`invoice_number`),
  KEY `invoices_company_id_status_index` (`company_id`,`status`),
  KEY `invoices_company_id_invoice_date_index` (`company_id`,`invoice_date`),
  CONSTRAINT `invoices_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `invoices_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoices`
--

LOCK TABLES `invoices` WRITE;
/*!40000 ALTER TABLE `invoices` DISABLE KEYS */;
INSERT INTO `invoices` VALUES (1,'INV-2024-001',1,1,'2026-03-26','2026-04-25',12750.00,2250.00,15000.00,15000.00,0.00,'paid','paid',NULL,NULL,'SAR',1.0000,'2026-03-26 07:53:33','2026-03-26 07:53:33'),(2,'INV-2024-002',2,1,'2026-03-21','2026-04-20',7225.00,1275.00,8500.00,4250.00,4250.00,'partial','partial',NULL,NULL,'SAR',1.0000,'2026-03-26 07:53:33','2026-03-26 07:53:33'),(3,'INV-2024-003',3,1,'2026-03-16','2026-04-15',10455.00,1845.00,12300.00,0.00,12300.00,'sent','pending',NULL,NULL,'SAR',1.0000,'2026-03-26 07:53:33','2026-03-26 07:53:33');
/*!40000 ALTER TABLE `invoices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_batches` (
  `id` varchar(191) NOT NULL,
  `name` varchar(191) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(191) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `journal_entries`
--

DROP TABLE IF EXISTS `journal_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `journal_entries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `entry_number` varchar(20) NOT NULL,
  `entry_date` date NOT NULL,
  `description` text DEFAULT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `source_type` varchar(50) DEFAULT NULL,
  `source_id` bigint(20) unsigned DEFAULT NULL,
  `entry_type` varchar(20) NOT NULL DEFAULT 'manual',
  `entry_origin` varchar(20) NOT NULL DEFAULT 'manual',
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `total_debit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_credit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `company_id` bigint(20) unsigned NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `posted_by` bigint(20) unsigned DEFAULT NULL,
  `posted_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `journal_entries_company_id_entry_number_unique` (`company_id`,`entry_number`),
  KEY `journal_entries_created_by_foreign` (`created_by`),
  KEY `journal_entries_posted_by_foreign` (`posted_by`),
  KEY `journal_entries_company_id_entry_date_index` (`company_id`,`entry_date`),
  KEY `journal_entries_company_id_status_index` (`company_id`,`status`),
  KEY `journal_entries_company_id_entry_origin_index` (`company_id`,`entry_origin`),
  KEY `journal_entries_source_type_source_id_index` (`source_type`,`source_id`),
  CONSTRAINT `journal_entries_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `journal_entries_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `journal_entries_posted_by_foreign` FOREIGN KEY (`posted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `journal_entries`
--

LOCK TABLES `journal_entries` WRITE;
/*!40000 ALTER TABLE `journal_entries` DISABLE KEYS */;
/*!40000 ALTER TABLE `journal_entries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `journal_lines`
--

DROP TABLE IF EXISTS `journal_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `journal_lines` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `journal_entry_id` bigint(20) unsigned NOT NULL,
  `account_id` bigint(20) unsigned NOT NULL,
  `description` varchar(300) DEFAULT NULL,
  `debit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `credit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `cost_center` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `journal_lines_journal_entry_id_index` (`journal_entry_id`),
  KEY `journal_lines_account_id_index` (`account_id`),
  CONSTRAINT `journal_lines_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`),
  CONSTRAINT `journal_lines_journal_entry_id_foreign` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `journal_lines`
--

LOCK TABLES `journal_lines` WRITE;
/*!40000 ALTER TABLE `journal_lines` DISABLE KEYS */;
/*!40000 ALTER TABLE `journal_lines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(191) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_03_24_085502_create_companies_table',1),(5,'2026_03_24_085522_create_accounts_table',1),(6,'2026_03_24_085524_create_journal_entries_table',1),(7,'2026_03_24_085528_create_journal_lines_table',1),(8,'2026_03_24_085529_create_customers_table',1),(9,'2026_03_24_085536_create_suppliers_table',1),(10,'2026_03_24_085538_create_products_table',1),(11,'2026_03_24_085543_create_invoices_table',1),(12,'2026_03_24_085548_create_invoice_items_table',1),(13,'2026_03_24_085601_modify_users_table_add_company_fields',1),(14,'2026_03_24_092838_create_purchases_table',1),(15,'2026_03_24_093842_create_employees_table',1),(16,'2026_03_25_000000_create_roles_and_permissions_tables',1),(17,'2026_03_25_010000_add_must_change_password_to_users_table',1),(18,'2026_03_25_120000_create_tax_settings_table',1),(19,'2026_03_25_130000_expand_products_table',1),(20,'2026_03_25_140000_add_supplier_id_to_products_table',1),(21,'2026_03_25_150000_create_purchase_items_table',1),(22,'2026_03_25_170000_add_source_fields_to_journal_entries_table',1),(23,'2026_03_25_171000_create_expenses_table',1),(24,'2026_03_25_172000_add_columns_to_invoice_items_table',1);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(191) NOT NULL,
  `token` varchar(191) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permission_role`
--

DROP TABLE IF EXISTS `permission_role`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permission_role` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `role_id` bigint(20) unsigned NOT NULL,
  `permission_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permission_role_role_id_permission_id_unique` (`role_id`,`permission_id`),
  KEY `permission_role_permission_id_foreign` (`permission_id`),
  CONSTRAINT `permission_role_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `permission_role_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permission_role`
--

LOCK TABLES `permission_role` WRITE;
/*!40000 ALTER TABLE `permission_role` DISABLE KEYS */;
INSERT INTO `permission_role` VALUES (1,1,1,'2026-03-26 07:53:32','2026-03-26 07:53:32'),(2,1,2,'2026-03-26 07:53:32','2026-03-26 07:53:32'),(3,1,3,'2026-03-26 07:53:32','2026-03-26 07:53:32'),(4,1,4,'2026-03-26 07:53:32','2026-03-26 07:53:32'),(5,1,5,'2026-03-26 07:53:32','2026-03-26 07:53:32'),(6,1,6,'2026-03-26 07:53:32','2026-03-26 07:53:32'),(7,1,7,'2026-03-26 07:53:32','2026-03-26 07:53:32'),(8,1,8,'2026-03-26 07:53:32','2026-03-26 07:53:32'),(9,1,9,'2026-03-26 07:53:32','2026-03-26 07:53:32'),(10,1,10,'2026-03-26 07:53:32','2026-03-26 07:53:32'),(11,1,11,'2026-03-26 07:53:32','2026-03-26 07:53:32'),(12,1,12,'2026-03-26 07:53:32','2026-03-26 07:53:32'),(13,1,13,'2026-03-26 07:53:32','2026-03-26 07:53:32'),(14,1,14,'2026-03-26 07:53:32','2026-03-26 07:53:32'),(15,2,1,'2026-03-26 07:53:32','2026-03-26 07:53:32'),(16,2,5,'2026-03-26 07:53:32','2026-03-26 07:53:32'),(17,2,3,'2026-03-26 07:53:32','2026-03-26 07:53:32'),(18,2,6,'2026-03-26 07:53:32','2026-03-26 07:53:32'),(19,2,7,'2026-03-26 07:53:32','2026-03-26 07:53:32'),(20,2,8,'2026-03-26 07:53:32','2026-03-26 07:53:32'),(21,2,9,'2026-03-26 07:53:32','2026-03-26 07:53:32'),(22,2,10,'2026-03-26 07:53:32','2026-03-26 07:53:32'),(23,2,11,'2026-03-26 07:53:32','2026-03-26 07:53:32'),(24,2,12,'2026-03-26 07:53:32','2026-03-26 07:53:32'),(25,2,13,'2026-03-26 07:53:32','2026-03-26 07:53:32'),(26,2,14,'2026-03-26 07:53:32','2026-03-26 07:53:32'),(27,3,5,'2026-03-26 07:53:32','2026-03-26 07:53:32'),(28,3,6,'2026-03-26 07:53:32','2026-03-26 07:53:32'),(29,3,7,'2026-03-26 07:53:32','2026-03-26 07:53:32'),(30,3,8,'2026-03-26 07:53:32','2026-03-26 07:53:32'),(31,3,9,'2026-03-26 07:53:32','2026-03-26 07:53:32'),(32,3,10,'2026-03-26 07:53:32','2026-03-26 07:53:32'),(33,3,11,'2026-03-26 07:53:32','2026-03-26 07:53:32'),(34,4,13,'2026-03-26 07:53:32','2026-03-26 07:53:32'),(35,4,14,'2026-03-26 07:53:32','2026-03-26 07:53:32'),(36,4,5,'2026-03-26 07:53:32','2026-03-26 07:53:32'),(37,5,8,'2026-03-26 07:53:32','2026-03-26 07:53:32'),(38,5,9,'2026-03-26 07:53:32','2026-03-26 07:53:32'),(39,5,5,'2026-03-26 07:53:32','2026-03-26 07:53:32'),(40,6,5,'2026-03-26 07:53:32','2026-03-26 07:53:32');
/*!40000 ALTER TABLE `permission_role` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permission_user`
--

DROP TABLE IF EXISTS `permission_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permission_user` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `permission_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permission_user_permission_id_user_id_unique` (`permission_id`,`user_id`),
  KEY `permission_user_user_id_foreign` (`user_id`),
  CONSTRAINT `permission_user_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `permission_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permission_user`
--

LOCK TABLES `permission_user` WRITE;
/*!40000 ALTER TABLE `permission_user` DISABLE KEYS */;
INSERT INTO `permission_user` VALUES (1,9,6,'2026-03-26 07:53:34','2026-03-26 07:53:34'),(2,11,6,'2026-03-26 07:53:34','2026-03-26 07:53:34');
/*!40000 ALTER TABLE `permission_user` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `display_name` varchar(100) NOT NULL,
  `group` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_unique` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES (1,'manage_users','إدارة المستخدمين','team','إنشاء المستخدمين وتعديلهم وتعطيلهم','2026-03-26 07:53:32','2026-03-26 07:53:32'),(2,'manage_roles','إدارة الأدوار','team','تعديل الأدوار والصلاحيات','2026-03-26 07:53:32','2026-03-26 07:53:32'),(3,'manage_settings','إدارة الإعدادات','settings','تعديل إعدادات الشركة','2026-03-26 07:53:32','2026-03-26 07:53:32'),(4,'manage_subscription','إدارة الاشتراك','settings','إدارة الباقة والفوترة','2026-03-26 07:53:32','2026-03-26 07:53:32'),(5,'view_reports','عرض التقارير','reports','الوصول إلى التقارير المالية','2026-03-26 07:53:32','2026-03-26 07:53:32'),(6,'manage_accounts','إدارة شجرة الحسابات','accounting','إدارة الحسابات المحاسبية','2026-03-26 07:53:32','2026-03-26 07:53:32'),(7,'manage_journal_entries','إدارة القيود','accounting','إنشاء وتعديل القيود المحاسبية','2026-03-26 07:53:32','2026-03-26 07:53:32'),(8,'manage_invoices','إدارة الفواتير','sales','إنشاء وتعديل فواتير المبيعات','2026-03-26 07:53:32','2026-03-26 07:53:32'),(9,'manage_customers','إدارة العملاء','sales','إدارة العملاء','2026-03-26 07:53:32','2026-03-26 07:53:32'),(10,'manage_purchases','إدارة المشتريات','procurement','إنشاء وتعديل المشتريات','2026-03-26 07:53:32','2026-03-26 07:53:32'),(11,'manage_suppliers','إدارة الموردين','procurement','إدارة الموردين','2026-03-26 07:53:32','2026-03-26 07:53:32'),(12,'manage_products','إدارة المنتجات','inventory','إدارة المنتجات والمخزون','2026-03-26 07:53:32','2026-03-26 07:53:32'),(13,'manage_employees','إدارة الموظفين','hr','إدارة ملفات الموظفين','2026-03-26 07:53:32','2026-03-26 07:53:32'),(14,'manage_payroll','إدارة الرواتب','hr','تشغيل الرواتب وإدارة التعويضات','2026-03-26 07:53:32','2026-03-26 07:53:32');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `products` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned DEFAULT NULL,
  `supplier_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(191) DEFAULT NULL,
  `name_ar` varchar(191) DEFAULT NULL,
  `code` varchar(50) DEFAULT NULL,
  `type` varchar(20) NOT NULL DEFAULT 'product',
  `unit` varchar(50) NOT NULL DEFAULT 'وحدة',
  `cost_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `sell_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `stock_quantity` decimal(12,2) NOT NULL DEFAULT 0.00,
  `min_stock` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tax_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `products_company_id_index` (`company_id`),
  KEY `products_supplier_id_foreign` (`supplier_id`),
  CONSTRAINT `products_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_items`
--

DROP TABLE IF EXISTS `purchase_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `purchase_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned DEFAULT NULL,
  `description` varchar(191) NOT NULL,
  `quantity` decimal(12,2) NOT NULL DEFAULT 1.00,
  `unit_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tax_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_items_purchase_id_foreign` (`purchase_id`),
  KEY `purchase_items_product_id_foreign` (`product_id`),
  CONSTRAINT `purchase_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_items_purchase_id_foreign` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_items`
--

LOCK TABLES `purchase_items` WRITE;
/*!40000 ALTER TABLE `purchase_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `purchase_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchases`
--

DROP TABLE IF EXISTS `purchases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchases` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `purchase_number` varchar(50) NOT NULL,
  `supplier_id` bigint(20) unsigned NOT NULL,
  `company_id` bigint(20) unsigned NOT NULL,
  `purchase_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `balance_due` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `payment_status` varchar(20) NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `terms` text DEFAULT NULL,
  `currency` varchar(5) NOT NULL DEFAULT 'SAR',
  `exchange_rate` decimal(10,4) NOT NULL DEFAULT 1.0000,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchases_supplier_id_foreign` (`supplier_id`),
  KEY `purchases_company_id_purchase_number_index` (`company_id`,`purchase_number`),
  KEY `purchases_company_id_status_index` (`company_id`,`status`),
  KEY `purchases_company_id_purchase_date_index` (`company_id`,`purchase_date`),
  CONSTRAINT `purchases_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchases_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchases`
--

LOCK TABLES `purchases` WRITE;
/*!40000 ALTER TABLE `purchases` DISABLE KEYS */;
INSERT INTO `purchases` VALUES (1,'PUR-2024-001',1,1,'2026-03-26','2026-04-10',6800.00,1200.00,8000.00,0.00,8000.00,'approved','pending',NULL,NULL,'SAR',1.0000,'2026-03-26 07:53:33','2026-03-26 07:53:33'),(2,'PUR-2024-002',2,1,'2026-03-23','2026-04-07',4420.00,780.00,5200.00,1560.00,3640.00,'partial','partial',NULL,NULL,'SAR',1.0000,'2026-03-26 07:53:33','2026-03-26 07:53:33'),(3,'PUR-2024-003',3,1,'2026-03-20','2026-04-04',8330.00,1470.00,9800.00,9800.00,0.00,'paid','paid',NULL,NULL,'SAR',1.0000,'2026-03-26 07:53:33','2026-03-26 07:53:33');
/*!40000 ALTER TABLE `purchases` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_user`
--

DROP TABLE IF EXISTS `role_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_user` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `role_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_user_role_id_user_id_unique` (`role_id`,`user_id`),
  KEY `role_user_user_id_foreign` (`user_id`),
  CONSTRAINT `role_user_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_user`
--

LOCK TABLES `role_user` WRITE;
/*!40000 ALTER TABLE `role_user` DISABLE KEYS */;
INSERT INTO `role_user` VALUES (1,1,1,'2026-03-26 07:53:33','2026-03-26 07:53:33'),(2,6,2,'2026-03-26 07:53:33','2026-03-26 07:53:33'),(3,3,3,'2026-03-26 07:53:34','2026-03-26 07:53:34'),(4,4,4,'2026-03-26 07:53:34','2026-03-26 07:53:34'),(5,5,5,'2026-03-26 07:53:34','2026-03-26 07:53:34'),(6,6,6,'2026-03-26 07:53:34','2026-03-26 07:53:34');
/*!40000 ALTER TABLE `role_user` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `display_name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_unique` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'owner','مالك الشركة','وصول كامل إلى النظام وإدارة الفريق والاشتراك','2026-03-26 07:53:32','2026-03-26 07:53:32'),(2,'admin','مدير','إدارة التشغيل اليومي للشركة','2026-03-26 07:53:32','2026-03-26 07:53:32'),(3,'accountant','محاسب','إدارة العمليات المحاسبية والفواتير والتقارير','2026-03-26 07:53:32','2026-03-26 07:53:32'),(4,'hr','موارد بشرية','إدارة الموظفين والرواتب','2026-03-26 07:53:32','2026-03-26 07:53:32'),(5,'sales','مبيعات','إدارة العملاء والفواتير','2026-03-26 07:53:32','2026-03-26 07:53:32'),(6,'viewer','مشاهد','وصول للقراءة فقط','2026-03-26 07:53:32','2026-03-26 07:53:32');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` varchar(191) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `suppliers`
--

DROP TABLE IF EXISTS `suppliers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `suppliers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `name_ar` varchar(200) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `tax_number` varchar(50) DEFAULT NULL,
  `credit_limit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `company_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `suppliers_company_id_email_unique` (`company_id`,`email`),
  UNIQUE KEY `suppliers_company_id_code_unique` (`company_id`,`code`),
  KEY `suppliers_company_id_is_active_index` (`company_id`,`is_active`),
  CONSTRAINT `suppliers_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `suppliers`
--

LOCK TABLES `suppliers` WRITE;
/*!40000 ALTER TABLE `suppliers` DISABLE KEYS */;
INSERT INTO `suppliers` VALUES (1,NULL,'مورد التقنية',NULL,'supply@tech.com','+966501234580',NULL,'جدة، المملكة العربية السعودية','جدة','SA',NULL,0.00,0.00,1,1,'2026-03-26 07:53:33','2026-03-26 07:53:33'),(2,NULL,'شركة المواد الخام',NULL,'supply@raw.com','+966501234581',NULL,'جدة، المملكة العربية السعودية','جدة','SA',NULL,0.00,0.00,1,1,'2026-03-26 07:53:33','2026-03-26 07:53:33'),(3,NULL,'مورد الخدمات',NULL,'supply@service.com','+966501234582',NULL,'جدة، المملكة العربية السعودية','جدة','SA',NULL,0.00,0.00,1,1,'2026-03-26 07:53:33','2026-03-26 07:53:33');
/*!40000 ALTER TABLE `suppliers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tax_settings`
--

DROP TABLE IF EXISTS `tax_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tax_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tax_name` varchar(100) NOT NULL,
  `tax_name_ar` varchar(100) DEFAULT NULL,
  `tax_type` varchar(50) NOT NULL DEFAULT 'vat',
  `rate` decimal(8,2) NOT NULL DEFAULT 0.00,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `account_id` bigint(20) unsigned DEFAULT NULL,
  `company_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tax_settings_account_id_foreign` (`account_id`),
  KEY `tax_settings_company_id_is_default_index` (`company_id`,`is_default`),
  CONSTRAINT `tax_settings_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tax_settings_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tax_settings`
--

LOCK TABLES `tax_settings` WRITE;
/*!40000 ALTER TABLE `tax_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `tax_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `email` varchar(191) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(191) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'user',
  `language` varchar(5) NOT NULL DEFAULT 'ar',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `company_id` bigint(20) unsigned DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_company_id_email_index` (`company_id`,`email`),
  CONSTRAINT `users_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'أحمد محمد','admin@test.com','2026-03-26 07:53:33','$2y$12$riWBFzSXCoX4Syv80DePoOg3j5FMKqQyq5NPhUa3k49tBbK84K0LS',NULL,'2026-03-26 07:53:33','2026-03-26 07:53:33','أحمد','محمد','owner','ar',1,0,1,NULL),(2,'محمد علي','user@test.com','2026-03-26 07:53:33','$2y$12$mHaQx58tnYmxktIfXf0Upulavz12sNwOOGJ3vhP2tL.1g9IA.zx2i',NULL,'2026-03-26 07:53:33','2026-03-26 07:53:33','محمد','علي','viewer','ar',1,1,1,NULL),(3,'سارة المحاسبة','accountant@test.com','2026-03-26 07:53:34','$2y$12$aRzzvoAe81QBv4AsLLB5BOdlqweFg4MDkbgdJ2/s.DshY14Ybwn/i',NULL,'2026-03-26 07:53:34','2026-03-26 07:53:34','سارة','المحاسبة','accountant','ar',1,1,1,NULL),(4,'نورة الموارد','hr@test.com','2026-03-26 07:53:34','$2y$12$aVQ/VODJv09b7XrW9cCoEO10ijF3A/qJm6zjZFn7rQEKMVo5cx5bS',NULL,'2026-03-26 07:53:34','2026-03-26 07:53:34','نورة','الموارد','hr','ar',1,1,1,NULL),(5,'خالد المبيعات','sales@test.com','2026-03-26 07:53:34','$2y$12$yNn9PWOmMEpFHSBeaBde2eZ4eP0mSGWZc9tgiZGsNXzaeW46DA03C',NULL,'2026-03-26 07:53:34','2026-03-26 07:53:34','خالد','المبيعات','sales','ar',1,1,1,NULL),(6,'ريم التشغيل','ops@test.com','2026-03-26 07:53:34','$2y$12$BzmYnZdO5Ff/P.wrq1LhGOnoxjCPLtHWRAgc2DzqfDoGfY1.xj5EW',NULL,'2026-03-26 07:53:34','2026-03-26 07:53:34','ريم','التشغيل','viewer','ar',1,1,1,NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'laravel_accounting'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-03-26 13:54:32
