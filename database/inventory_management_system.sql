-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 19, 2026 at 07:24 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `inventory_management_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `name`, `email`, `phone`, `address`, `created_at`) VALUES
(1, 'Walk-in Customer', 'walkin@example.com', '0000000000', 'Store Front', '2026-03-18 05:34:53'),
(2, 'John Doe', 'john@example.com', '1234567890', '123 Street, NY', '2026-03-18 05:34:53'),
(3, 'Jane Smith', 'jane@example.com', '9876543210', '456 Avenue, CA', '2026-03-18 05:34:53');

-- --------------------------------------------------------

--
-- Table structure for table `distributors`
--

CREATE TABLE `distributors` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `company_name` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `distributors`
--

INSERT INTO `distributors` (`id`, `user_id`, `company_name`, `created_at`, `updated_at`) VALUES
(1, 2, 'Tech Supplies Co.', '2026-03-17 09:39:25', '2026-03-17 09:39:25'),
(2, 3, 'Gadget World Ltd.', '2026-03-17 09:39:25', '2026-03-17 09:39:25'),
(3, 4, 'Hardware Hub', '2026-03-17 09:39:25', '2026-03-17 09:39:25'),
(4, 5, 'Office Essentials Inc.', '2026-03-17 09:39:25', '2026-03-17 09:39:25'),
(5, 6, 'Digital Gear Pvt Ltd.', '2026-03-17 09:39:25', '2026-03-17 09:39:25');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `order_type` enum('shop','online') DEFAULT 'shop',
  `total_amount` decimal(10,2) DEFAULT NULL,
  `discount` decimal(10,2) DEFAULT 0.00,
  `delivery_charges` decimal(10,2) DEFAULT 0.00,
  `final_amount` decimal(10,2) DEFAULT NULL,
  `status` enum('pending','completed','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `customer_id`, `created_by`, `order_type`, `total_amount`, `discount`, `delivery_charges`, `final_amount`, `status`, `created_at`, `updated_at`) VALUES
(1, 9, 7, 'shop', 1200.00, 50.00, 0.00, 1150.00, 'completed', '2026-03-17 09:39:25', '2026-03-17 09:39:25'),
(2, 10, 8, 'online', 600.00, 0.00, 0.00, 600.00, 'completed', '2026-03-17 09:39:25', '2026-03-17 09:39:25'),
(3, 11, 7, '', 900.00, 100.00, 0.00, 800.00, 'completed', '2026-03-17 09:39:25', '2026-03-18 06:18:43'),
(4, 12, 8, 'shop', 450.00, 0.00, 0.00, 450.00, 'completed', '2026-03-17 09:39:25', '2026-03-17 09:39:25'),
(5, 9, 7, 'online', 750.00, 50.00, 0.00, 700.00, 'pending', '2026-03-17 09:39:25', '2026-03-17 09:39:25'),
(6, 3, 1, 'shop', 255.00, 5.00, 0.00, 250.00, 'completed', '2026-03-18 05:40:11', '2026-03-18 06:20:44');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `total` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `price`, `total`, `created_at`) VALUES
(1, 1, 1, 1, 700.00, 700.00, '2026-03-17 09:39:26'),
(2, 1, 3, 10, 25.00, 250.00, '2026-03-17 09:39:26'),
(3, 1, 5, 1, 150.00, 150.00, '2026-03-17 09:39:26'),
(4, 2, 2, 2, 300.00, 600.00, '2026-03-17 09:39:26'),
(5, 3, 4, 10, 50.00, 500.00, '2026-03-17 09:39:26'),
(6, 3, 6, 5, 60.00, 300.00, '2026-03-17 09:39:26'),
(7, 4, 7, 5, 35.00, 175.00, '2026-03-17 09:39:26'),
(8, 4, 8, 1, 180.00, 180.00, '2026-03-17 09:39:26'),
(9, 4, 9, 1, 70.00, 70.00, '2026-03-17 09:39:26'),
(10, 5, 1, 1, 700.00, 700.00, '2026-03-17 09:39:26'),
(11, 6, 11, 3, 85.00, 255.00, '2026-03-18 05:40:11');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `sale_id` int(11) DEFAULT NULL,
  `payment_method` enum('cash','online','bank','card') DEFAULT 'cash',
  `transaction_id` varchar(100) DEFAULT NULL,
  `payment_proof` varchar(255) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `status` enum('paid','unpaid','partial') DEFAULT 'unpaid',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `order_id`, `sale_id`, `payment_method`, `transaction_id`, `payment_proof`, `amount`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'cash', NULL, NULL, 1150.00, 'paid', '2026-03-17 09:39:26', '2026-03-18 07:23:29'),
(2, 2, 2, 'online', NULL, NULL, 600.00, 'paid', '2026-03-17 09:39:26', '2026-03-18 07:23:29'),
(3, 3, 3, 'cash', NULL, NULL, 400.00, 'partial', '2026-03-17 09:39:26', '2026-03-18 07:23:29'),
(4, 4, 4, 'online', NULL, NULL, 450.00, 'paid', '2026-03-17 09:39:26', '2026-03-18 07:23:29'),
(5, 5, 5, 'online', NULL, NULL, 350.00, 'partial', '2026-03-17 09:39:26', '2026-03-18 07:23:29'),
(6, 3, 3, 'cash', '', NULL, 400.00, 'paid', '2026-03-18 06:18:43', '2026-03-18 07:23:29'),
(7, 6, 6, 'online', '120.2530563556', 'uploads/payments/PROOF_1773814844_6.webp', 250.00, 'paid', '2026-03-18 06:20:44', '2026-03-18 07:23:29');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `cost_price` decimal(10,2) NOT NULL,
  `selling_price` decimal(10,2) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `sku`, `cost_price`, `selling_price`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Laptop Dell Inspiron', 'DELL-INSP-001', 499.99, 700.00, 'active', '2026-03-17 09:39:25', '2026-03-18 05:03:49'),
(2, 'Smartphone Samsung Galaxy', 'SAMS-GAL-001', 200.00, 300.00, 'active', '2026-03-17 09:39:25', '2026-03-17 09:39:25'),
(3, 'Wireless Mouse Logitech', 'LOGI-MOUSE-001', 15.00, 25.00, 'active', '2026-03-17 09:39:25', '2026-03-17 09:39:25'),
(4, 'Keyboard Mechanical', 'MECH-KEY-001', 30.00, 50.00, 'active', '2026-03-17 09:39:25', '2026-03-17 09:39:25'),
(5, '27-inch Monitor LG', 'LG-MON-27-001', 150.00, 220.00, 'active', '2026-03-17 09:39:25', '2026-03-17 09:39:25'),
(6, 'External Hard Drive 1TB', 'EXT-HDD-1TB', 60.00, 90.00, 'active', '2026-03-17 09:39:25', '2026-03-17 09:39:25'),
(7, 'USB-C Hub', 'USBC-HUB-001', 20.00, 35.00, 'active', '2026-03-17 09:39:25', '2026-03-17 09:39:25'),
(8, 'Gaming Chair', 'GCH-001', 100.00, 180.00, 'active', '2026-03-17 09:39:25', '2026-03-17 09:39:25'),
(9, 'Webcam HD', 'WEBCAM-001', 40.00, 70.00, 'active', '2026-03-17 09:39:25', '2026-03-17 09:39:25'),
(10, 'Headphones Wireless', 'HEAD-WIRE-001', 25.00, 50.00, 'active', '2026-03-17 09:39:25', '2026-03-17 09:39:25'),
(11, 'Detol', 'DET-INP-001', 76.00, 85.00, 'active', '2026-03-18 05:04:33', '2026-03-18 05:04:33');

-- --------------------------------------------------------

--
-- Table structure for table `product_stock`
--

CREATE TABLE `product_stock` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_stock`
--

INSERT INTO `product_stock` (`id`, `product_id`, `quantity`, `created_at`, `updated_at`) VALUES
(1, 1, 20, '2026-03-17 09:39:25', '2026-03-17 09:39:25'),
(2, 2, 30, '2026-03-17 09:39:25', '2026-03-17 09:39:25'),
(3, 3, 50, '2026-03-17 09:39:25', '2026-03-17 09:39:25'),
(4, 4, 40, '2026-03-17 09:39:25', '2026-03-17 09:39:25'),
(5, 5, 15, '2026-03-17 09:39:25', '2026-03-17 09:39:25'),
(6, 6, 25, '2026-03-17 09:39:25', '2026-03-17 09:39:25'),
(7, 7, 35, '2026-03-17 09:39:25', '2026-03-17 09:39:25'),
(8, 8, 10, '2026-03-17 09:39:25', '2026-03-17 09:39:25'),
(9, 9, 30, '2026-03-17 09:39:25', '2026-03-17 09:39:25'),
(10, 10, 50, '2026-03-17 09:39:25', '2026-03-17 09:39:25'),
(11, 11, 17, '2026-03-18 05:24:18', '2026-03-18 05:40:11');

-- --------------------------------------------------------

--
-- Table structure for table `product_transactions`
--

CREATE TABLE `product_transactions` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `type` enum('IN','OUT') NOT NULL,
  `quantity` int(11) NOT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_transactions`
--

INSERT INTO `product_transactions` (`id`, `product_id`, `type`, `quantity`, `reference_type`, `reference_id`, `note`, `created_by`, `created_at`) VALUES
(1, 1, 'IN', 20, 'initial_stock', NULL, NULL, 1, '2026-03-17 09:39:25'),
(2, 2, 'IN', 30, 'initial_stock', NULL, NULL, 1, '2026-03-17 09:39:25'),
(3, 3, 'IN', 50, 'initial_stock', NULL, NULL, 1, '2026-03-17 09:39:25'),
(4, 4, 'IN', 40, 'initial_stock', NULL, NULL, 1, '2026-03-17 09:39:25'),
(5, 5, 'IN', 15, 'initial_stock', NULL, NULL, 1, '2026-03-17 09:39:25'),
(6, 6, 'IN', 25, 'initial_stock', NULL, NULL, 1, '2026-03-17 09:39:25'),
(7, 7, 'IN', 35, 'initial_stock', NULL, NULL, 1, '2026-03-17 09:39:25'),
(8, 8, 'IN', 10, 'initial_stock', NULL, NULL, 1, '2026-03-17 09:39:25'),
(9, 9, 'IN', 30, 'initial_stock', NULL, NULL, 1, '2026-03-17 09:39:25'),
(10, 10, 'IN', 50, 'initial_stock', NULL, NULL, 1, '2026-03-17 09:39:25'),
(11, 1, 'OUT', 1, 'order', 1, NULL, 7, '2026-03-17 09:39:26'),
(12, 3, 'OUT', 10, 'order', 1, NULL, 7, '2026-03-17 09:39:26'),
(13, 5, 'OUT', 1, 'order', 1, NULL, 7, '2026-03-17 09:39:26'),
(14, 2, 'OUT', 2, 'order', 2, NULL, 8, '2026-03-17 09:39:26'),
(15, 4, 'OUT', 10, 'order', 3, NULL, 7, '2026-03-17 09:39:26'),
(16, 6, 'OUT', 5, 'order', 3, NULL, 7, '2026-03-17 09:39:26'),
(17, 7, 'OUT', 5, 'order', 4, NULL, 8, '2026-03-17 09:39:26'),
(18, 8, 'OUT', 1, 'order', 4, NULL, 8, '2026-03-17 09:39:26'),
(19, 9, 'OUT', 1, 'order', 4, NULL, 8, '2026-03-17 09:39:26'),
(20, 1, 'OUT', 1, 'order', 5, NULL, 7, '2026-03-17 09:39:26'),
(21, 11, 'IN', 20, 'manual', NULL, 'stock Added!', 1, '2026-03-18 05:24:18'),
(22, 11, 'OUT', 3, 'order', 6, 'Sale for Order #6', 1, '2026-03-18 05:40:11');

-- --------------------------------------------------------

--
-- Table structure for table `profits`
--

CREATE TABLE `profits` (
  `id` int(11) NOT NULL,
  `reference_type` enum('order','product') NOT NULL,
  `reference_id` int(11) NOT NULL,
  `cost_price` decimal(10,2) NOT NULL,
  `selling_price` decimal(10,2) NOT NULL,
  `discount` decimal(10,2) DEFAULT 0.00,
  `profit_amount` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `profits`
--

INSERT INTO `profits` (`id`, `reference_type`, `reference_id`, `cost_price`, `selling_price`, `discount`, `profit_amount`, `created_at`, `updated_at`) VALUES
(1, 'order', 1, 799.99, 1150.00, 50.00, 350.01, '2026-03-18 08:52:00', '2026-03-18 08:53:19'),
(2, 'order', 2, 400.00, 600.00, 0.00, 200.00, '2026-03-18 08:52:00', '2026-03-18 08:53:19'),
(3, 'order', 3, 600.00, 800.00, 100.00, 200.00, '2026-03-18 08:52:01', '2026-03-18 08:53:19'),
(4, 'order', 4, 240.00, 450.00, 0.00, 210.00, '2026-03-18 08:52:01', '2026-03-18 08:53:19'),
(5, 'order', 6, 228.00, 250.00, 5.00, 22.00, '2026-03-18 08:52:01', '2026-03-18 08:53:19'),
(6, 'product', 1, 499.99, 700.00, 0.00, 200.01, '2026-03-18 08:52:42', '2026-03-18 08:52:45'),
(7, 'product', 2, 200.00, 300.00, 0.00, 100.00, '2026-03-18 08:52:42', '2026-03-18 08:52:45'),
(8, 'product', 3, 15.00, 25.00, 0.00, 10.00, '2026-03-18 08:52:42', '2026-03-18 08:52:45'),
(9, 'product', 4, 30.00, 50.00, 0.00, 20.00, '2026-03-18 08:52:42', '2026-03-18 08:52:45'),
(10, 'product', 5, 150.00, 220.00, 0.00, 70.00, '2026-03-18 08:52:42', '2026-03-18 08:52:45'),
(11, 'product', 6, 60.00, 90.00, 0.00, 30.00, '2026-03-18 08:52:42', '2026-03-18 08:52:45'),
(12, 'product', 7, 20.00, 35.00, 0.00, 15.00, '2026-03-18 08:52:42', '2026-03-18 08:52:45'),
(13, 'product', 8, 100.00, 180.00, 0.00, 80.00, '2026-03-18 08:52:42', '2026-03-18 08:52:45'),
(14, 'product', 9, 40.00, 70.00, 0.00, 30.00, '2026-03-18 08:52:42', '2026-03-18 08:52:45'),
(15, 'product', 10, 25.00, 50.00, 0.00, 25.00, '2026-03-18 08:52:42', '2026-03-18 08:52:45'),
(16, 'product', 11, 76.00, 85.00, 0.00, 9.00, '2026-03-18 08:52:42', '2026-03-18 08:52:45');

-- --------------------------------------------------------

--
-- Table structure for table `purchases`
--

CREATE TABLE `purchases` (
  `id` int(11) NOT NULL,
  `distributor_id` int(11) DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchases`
--

INSERT INTO `purchases` (`id`, `distributor_id`, `total_amount`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 3000.00, 1, '2026-03-17 09:39:25', '2026-03-17 09:39:25'),
(2, 2, 2500.00, 1, '2026-03-17 09:39:25', '2026-03-17 09:39:25'),
(3, 3, 1800.00, 1, '2026-03-17 09:39:25', '2026-03-17 09:39:25'),
(4, 4, 2200.00, 1, '2026-03-17 09:39:25', '2026-03-17 09:39:25'),
(5, 5, 1500.00, 1, '2026-03-17 09:39:25', '2026-03-17 09:39:25');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_items`
--

CREATE TABLE `purchase_items` (
  `id` int(11) NOT NULL,
  `purchase_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `cost_price` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_items`
--

INSERT INTO `purchase_items` (`id`, `purchase_id`, `product_id`, `quantity`, `cost_price`, `created_at`) VALUES
(1, 1, 1, 5, 500.00, '2026-03-17 09:39:25'),
(2, 1, 2, 10, 200.00, '2026-03-17 09:39:25'),
(3, 1, 3, 15, 15.00, '2026-03-17 09:39:25'),
(4, 2, 4, 20, 30.00, '2026-03-17 09:39:25'),
(5, 2, 5, 5, 150.00, '2026-03-17 09:39:25'),
(6, 2, 6, 10, 60.00, '2026-03-17 09:39:25'),
(7, 3, 7, 15, 20.00, '2026-03-17 09:39:25'),
(8, 3, 8, 5, 100.00, '2026-03-17 09:39:25'),
(9, 4, 9, 10, 40.00, '2026-03-17 09:39:25'),
(10, 4, 10, 20, 25.00, '2026-03-17 09:39:25'),
(11, 5, 3, 10, 15.00, '2026-03-17 09:39:25'),
(12, 5, 2, 5, 200.00, '2026-03-17 09:39:25');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `created_at`) VALUES
(1, 'admin', '2026-03-17 05:21:24'),
(2, 'distributor', '2026-03-17 05:21:24'),
(3, 'staff', '2026-03-17 05:21:24'),
(4, 'customer', '2026-03-17 05:21:24');

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT NULL,
  `discount` decimal(10,2) DEFAULT 0.00,
  `final_amount` decimal(10,2) DEFAULT NULL,
  `status` enum('pending','completed','cancelled') NOT NULL DEFAULT 'pending',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`id`, `order_id`, `customer_id`, `total_amount`, `discount`, `final_amount`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 9, 1200.00, 50.00, 1150.00, 'completed', 7, '2026-03-17 09:39:25', '2026-03-17 09:39:25'),
(2, 2, 10, 600.00, 0.00, 600.00, 'completed', 8, '2026-03-17 09:39:25', '2026-03-17 09:39:25'),
(3, 3, 11, 900.00, 100.00, 800.00, 'completed', 7, '2026-03-17 09:39:25', '2026-03-18 06:18:43'),
(4, 4, 12, 450.00, 0.00, 450.00, 'completed', 8, '2026-03-17 09:39:25', '2026-03-17 09:39:25'),
(5, 5, 9, 750.00, 50.00, 700.00, 'pending', 7, '2026-03-17 09:39:25', '2026-03-17 09:39:25'),
(6, 6, 3, 255.00, 5.00, 250.00, 'completed', 1, '2026-03-18 05:40:11', '2026-03-18 06:20:44');

-- --------------------------------------------------------

--
-- Table structure for table `sale_items`
--

CREATE TABLE `sale_items` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `total` decimal(10,2) GENERATED ALWAYS AS (`quantity` * `price`) STORED,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sale_items`
--

INSERT INTO `sale_items` (`id`, `sale_id`, `product_id`, `quantity`, `price`, `created_at`) VALUES
(1, 1, 1, 1, 700.00, '2026-03-18 07:23:29'),
(2, 1, 3, 10, 25.00, '2026-03-18 07:23:29'),
(3, 1, 5, 1, 150.00, '2026-03-18 07:23:29'),
(4, 2, 2, 2, 300.00, '2026-03-18 07:23:29'),
(5, 3, 4, 10, 50.00, '2026-03-18 07:23:29'),
(6, 3, 6, 5, 60.00, '2026-03-18 07:23:29'),
(7, 4, 7, 5, 35.00, '2026-03-18 07:23:29'),
(8, 4, 8, 1, 180.00, '2026-03-18 07:23:29'),
(9, 4, 9, 1, 70.00, '2026-03-18 07:23:29'),
(10, 5, 1, 1, 700.00, '2026-03-18 07:23:29'),
(11, 6, 11, 3, 85.00, '2026-03-18 07:23:29');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `role_id`, `name`, `email`, `phone`, `password`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 'Admin User', 'admin@example.com', '3000000001', 'admin123', 'active', '2026-03-17 09:39:25', '2026-03-17 09:41:27'),
(2, 2, 'Distributor One', 'dist1@example.com', '3001111111', 'password123', 'active', '2026-03-17 09:39:25', '2026-03-17 09:39:25'),
(3, 2, 'Distributor Two', 'dist2@example.com', '3002222222', 'password123', 'active', '2026-03-17 09:39:25', '2026-03-17 09:39:25'),
(4, 2, 'Distributor Three', 'dist3@example.com', '3003333333', 'password123', 'active', '2026-03-17 09:39:25', '2026-03-17 09:39:25'),
(5, 2, 'Distributor Four', 'dist4@example.com', '3004444444', 'password123', 'active', '2026-03-17 09:39:25', '2026-03-17 09:39:25'),
(6, 2, 'Distributor Five', 'dist5@example.com', '3005555555', 'password123', 'active', '2026-03-17 09:39:25', '2026-03-17 09:39:25'),
(7, 3, 'Staff One', 'staff1@example.com', '3006666666', 'password123', 'active', '2026-03-17 09:39:25', '2026-03-17 09:39:25'),
(8, 3, 'Staff Two', 'staff2@example.com', '3007777777', 'password123', 'active', '2026-03-17 09:39:25', '2026-03-17 09:39:25'),
(9, 4, 'Customer One', 'cust1@example.com', '3008888881', 'password123', 'active', '2026-03-17 09:39:25', '2026-03-17 09:39:25'),
(10, 4, 'Customer Two', 'cust2@example.com', '3008888882', 'password123', 'active', '2026-03-17 09:39:25', '2026-03-17 09:39:25'),
(11, 4, 'Customer Three', 'cust3@example.com', '3008888883', 'password123', 'active', '2026-03-17 09:39:25', '2026-03-17 09:39:25'),
(12, 4, 'Customer Four', 'cust4@example.com', '3008888884', 'password123', 'active', '2026-03-17 09:39:25', '2026-03-17 09:39:25');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `distributors`
--
ALTER TABLE `distributors`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `idx_order_id` (`order_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `fk_payment_sale` (`sale_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sku` (`sku`);

--
-- Indexes for table `product_stock`
--
ALTER TABLE `product_stock`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product_id` (`product_id`);

--
-- Indexes for table `product_transactions`
--
ALTER TABLE `product_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_transaction_product` (`product_id`);

--
-- Indexes for table `profits`
--
ALTER TABLE `profits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_reference_type` (`reference_type`),
  ADD KEY `idx_reference_id` (`reference_id`);

--
-- Indexes for table `purchases`
--
ALTER TABLE `purchases`
  ADD PRIMARY KEY (`id`),
  ADD KEY `distributor_id` (`distributor_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `purchase_items`
--
ALTER TABLE `purchase_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchase_id` (`purchase_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sale_id` (`sale_id`),
  ADD KEY `idx_sale_product` (`product_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `role_id` (`role_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `distributors`
--
ALTER TABLE `distributors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `product_stock`
--
ALTER TABLE `product_stock`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `product_transactions`
--
ALTER TABLE `product_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `profits`
--
ALTER TABLE `profits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `purchases`
--
ALTER TABLE `purchases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `purchase_items`
--
ALTER TABLE `purchase_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `distributors`
--
ALTER TABLE `distributors`
  ADD CONSTRAINT `distributors_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payment_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_stock`
--
ALTER TABLE `product_stock`
  ADD CONSTRAINT `product_stock_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_transactions`
--
ALTER TABLE `product_transactions`
  ADD CONSTRAINT `product_transactions_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_transactions_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `purchases`
--
ALTER TABLE `purchases`
  ADD CONSTRAINT `purchases_ibfk_1` FOREIGN KEY (`distributor_id`) REFERENCES `distributors` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `purchases_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `purchase_items`
--
ALTER TABLE `purchase_items`
  ADD CONSTRAINT `purchase_items_ibfk_1` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `purchase_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `sales_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `sales_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD CONSTRAINT `sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sale_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
