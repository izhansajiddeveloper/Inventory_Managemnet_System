<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include only database config
require_once 'config/db.php';

// If already logged in, redirect to dashboard based on role
if (isset($_SESSION['user_id'])) {
    $role_id = $_SESSION['user_role'] ?? 0;
    
    if ($role_id == 1) { // Admin
        header("Location: admin/dashboard.php");
        exit();
    }
    if ($role_id == 2) { // Distributor
        header("Location: distributor/dashboard.php");
        exit();
    }
    if ($role_id == 3) { // Staff
        header("Location: staff/dashboard.php");
        exit();
    }
    
    // Default redirect if role not matched
    header("Location: dashboard.php");
    exit();
}

$page_title = "Home";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Inventory Solutions</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            DEFAULT: '#2563eb', // blue-600
                            light: '#3b82f6', // blue-500
                            dark: '#1e40af', // blue-800
                        },
                        secondary: {
                            DEFAULT: '#10b981', // emerald-500
                            light: '#34d399', // emerald-400
                            dark: '#059669', // emerald-600
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; scroll-behavior: smooth; }
        
        /* Subtle Animations */
        .fade-in-up {
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.8s ease-out, transform 0.8s ease-out;
        }
        
        .fade-in-up.visible {
            opacity: 1;
            transform: translateY(0);
        }
        
        .delay-100 { transition-delay: 100ms; }
        .delay-200 { transition-delay: 200ms; }
        .delay-300 { transition-delay: 300ms; }

        .hover-lift {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .hover-lift:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -6px rgba(0, 0, 0, 0.1);
        }

        .gradient-text {
            background: linear-gradient(135deg, #2563eb, #10b981);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Floating Animation for Hero Image */
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
            100% { transform: translateY(0px); }
        }
        .animate-float {
            animation: float 6s ease-in-out infinite;
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 antialiased overflow-x-hidden">

    <!-- Navbar -->
    <nav class="bg-white/80 backdrop-blur-md shadow-sm border-b border-gray-100 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-20">
                <!-- Logo -->
                <div class="flex-shrink-0 flex items-center">
                    <a href="index.php" class="flex items-center gap-2 group">
                        <div class="w-10 h-10 bg-brand text-white rounded-lg flex items-center justify-center text-xl shadow-md group-hover:bg-brand-dark transition-all duration-300 group-hover:rotate-12">
                            <i class="fa-solid fa-boxes-stacked"></i>
                        </div>
                        <span class="font-bold text-xl text-gray-900 tracking-tight">Inventory<span class="text-brand">Pro</span></span>
                    </a>
                </div>

                <!-- Desktop Menu -->
                <div class="hidden md:flex items-center space-x-8">
                    <a href="#features" class="text-gray-600 hover:text-brand font-medium transition-colors">Features</a>
                    <a href="#how-it-works" class="text-gray-600 hover:text-brand font-medium transition-colors">Process</a>
                    <a href="#pricing" class="text-gray-600 hover:text-brand font-medium transition-colors">Pricing</a>
                    <a href="#contact" class="text-gray-600 hover:text-brand font-medium transition-colors">Contact</a>
                    
                    <div class="flex items-center gap-4 ml-4 pl-4 border-l border-gray-200">
                        <a href="auth/login.php" class="bg-brand hover:bg-brand-dark text-white px-8 py-2.5 rounded-lg font-bold transition-all shadow-lg shadow-blue-500/20 hover:shadow-blue-500/40 hover:-translate-y-0.5">
                            Login Portal
                        </a>
                    </div>
                </div>

                <!-- Mobile menu button -->
                <div class="flex items-center md:hidden">
                    <button type="button" id="mobile-menu-button" class="text-gray-500 hover:text-gray-900 focus:outline-none p-2">
                        <i class="fa-solid fa-bars-staggered text-2xl"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Menu -->
        <div class="md:hidden hidden bg-white border-t border-gray-100 absolute w-full" id="mobile-menu">
            <div class="px-4 pt-2 pb-6 space-y-2 shadow-lg">
                <a href="#features" class="block px-3 py-2 text-base font-medium text-gray-700 hover:text-brand hover:bg-gray-50 rounded-md">Features</a>
                <a href="#how-it-works" class="block px-3 py-2 text-base font-medium text-gray-700 hover:text-brand hover:bg-gray-50 rounded-md">Process</a>
                <a href="#pricing" class="block px-3 py-2 text-base font-medium text-gray-700 hover:text-brand hover:bg-gray-50 rounded-md">Pricing</a>
                <a href="#contact" class="block px-3 py-2 text-base font-medium text-gray-700 hover:text-brand hover:bg-gray-50 rounded-md">Contact</a>
                <div class="mt-4 pt-4 border-t border-gray-100">
                    <a href="auth/login.php" class="block text-center bg-brand text-white font-bold px-4 py-3 rounded-lg hover:bg-brand-dark">Login Portal</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="relative bg-white overflow-hidden py-24 lg:py-32">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
                <!-- Left Content -->
                <div class="fade-in-up">
                    <span class="inline-block py-1.5 px-4 rounded-full bg-blue-50 text-brand text-sm font-bold mb-6 shadow-sm border border-blue-100 uppercase tracking-wider">
                        <i class="fa-solid fa-bolt-lightning mr-2"></i> Powering 5000+ Businesses
                    </span>
                    <h1 class="text-5xl md:text-6xl lg:text-7xl font-extrabold text-gray-900 leading-[1.1] mb-8">
                        Smart Inventory <br> <span class="gradient-text">Scalable Future</span>
                    </h1>
                    <p class="text-xl text-gray-600 mb-10 leading-relaxed max-w-xl">
                        The only inventory management platform that thinks ahead. Automate your supply chain, reduce overheads, and focus on what matters—your growth.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-5 items-center">
                        <a href="auth/login.php" class="w-full sm:w-auto bg-brand hover:bg-brand-dark text-white px-10 py-4 rounded-xl font-bold transition-all shadow-xl shadow-blue-500/20 hover:shadow-blue-500/40 text-center text-lg flex items-center justify-center gap-3 group">
                            Enter Dashboard <i class="fa-solid fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
                        </a>
                        <a href="#how-it-works" class="w-full sm:w-auto bg-white hover:bg-gray-50 text-gray-800 border-2 border-gray-100 px-10 py-4 rounded-xl font-bold transition-all shadow-sm text-center text-lg">
                            See How it Works
                        </a>
                    </div>
                </div>

                <!-- Right Image -->
                <div class="relative fade-in-up delay-200">
                    <div class="absolute -inset-4 bg-gradient-to-r from-brand/20 to-secondary/20 rounded-[3rem] blur-2xl opacity-50"></div>
                    <div class="relative animate-float">
                        <img src="https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80" alt="Modern Warehouse Management" class="rounded-[2.5rem] shadow-2xl border-8 border-white">
                        
                        <!-- Floating Stats Card -->
                        <div class="absolute -bottom-10 -left-10 bg-white p-6 rounded-3xl shadow-2xl border border-gray-100 hidden md:block">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 bg-emerald-100 text-emerald-600 rounded-2xl flex items-center justify-center text-2xl">
                                    <i class="fa-solid fa-chart-line"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-gray-500 uppercase">Growth</p>
                                    <p class="text-2xl font-black text-gray-900">+12%</p>
                                </div>
                            </div>
                        </div>

                        <!-- Floating Notification -->
                        <div class="absolute -top-10 -right-5 bg-white p-5 rounded-2xl shadow-xl border border-gray-100 hidden md:block max-w-[200px]">
                            <div class="flex items-center gap-3 mb-2">
                                <div class="w-3 h-3 bg-red-500 rounded-full animate-pulse"></div>
                                <p class="text-xs font-bold text-gray-400">Stock Alert</p>
                            </div>
                            <p class="text-sm font-bold text-gray-800 leading-tight">iPhone 15 is running low in NY Warehouse</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-32 bg-white relative overflow-hidden">
        <div class="absolute -right-20 top-40 w-80 h-80 bg-brand/5 rounded-full blur-3xl"></div>
        <div class="absolute -left-20 bottom-40 w-80 h-80 bg-secondary/5 rounded-full blur-3xl"></div>
        
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-20 fade-in-up">
                <h2 class="text-4xl md:text-5xl font-extrabold text-gray-900 mb-6">Designed for Excellence</h2>
                <p class="text-lg text-gray-600 leading-relaxed">Stop fighting with spreadsheets. Our comprehensive toolkit gives you the visibility and control required for a modern enterprise.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-10">
                <!-- Feature Card 1 -->
                <div class="bg-gray-50 rounded-3xl p-10 border border-gray-100 hover-lift fade-in-up delay-100 group">
                    <div class="w-16 h-16 bg-white rounded-2xl shadow-sm text-brand flex justify-center items-center text-3xl mb-8 group-hover:bg-brand group-hover:text-white transition-all duration-300">
                        <i class="fa-solid fa-box-open"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-4 tracking-tight">Real-Time Inventory</h3>
                    <p class="text-gray-600 leading-relaxed">Automatic updates across all locations ensures your stock levels are always 100% accurate, 24/7.</p>
                </div>

                <!-- Feature Card 2 -->
                <div class="bg-gray-50 rounded-3xl p-10 border border-gray-100 hover-lift fade-in-up delay-200 group">
                    <div class="w-16 h-16 bg-white rounded-2xl shadow-sm text-secondary flex justify-center items-center text-3xl mb-8 group-hover:bg-secondary group-hover:text-white transition-all duration-300">
                        <i class="fa-solid fa-truck-fast"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-4 tracking-tight">Smart Shipping</h3>
                    <p class="text-gray-600 leading-relaxed">Integrate with major carriers to calculate rates, print labels, and track shipments in one dashboard.</p>
                </div>

                <!-- Feature Card 3 -->
                <div class="bg-gray-50 rounded-3xl p-10 border border-gray-100 hover-lift fade-in-up delay-300 group">
                    <div class="w-16 h-16 bg-white rounded-2xl shadow-sm text-orange-500 flex justify-center items-center text-3xl mb-8 group-hover:bg-orange-500 group-hover:text-white transition-all duration-300">
                        <i class="fa-solid fa-brain"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-4 tracking-tight">Predictive Insights</h3>
                    <p class="text-gray-600 leading-relaxed">AI-driven demand forecasting helps you anticipate stock needs before you even run out.</p>
                </div>

                <!-- Feature Card 4 -->
                <div class="bg-gray-50 rounded-3xl p-10 border border-gray-100 hover-lift fade-in-up group">
                    <div class="w-16 h-16 bg-white rounded-2xl shadow-sm text-purple-600 flex justify-center items-center text-3xl mb-8 group-hover:bg-purple-600 group-hover:text-white transition-all duration-300">
                        <i class="fa-solid fa-users-gear"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-4 tracking-tight">Multi-User Roles</h3>
                    <p class="text-gray-600 leading-relaxed">Granular permissions for staff, managers, and warehouse workers to keep your data secure.</p>
                </div>

                <!-- Feature Card 5 -->
                <div class="bg-gray-50 rounded-3xl p-10 border border-gray-100 hover-lift fade-in-up delay-100 group">
                    <div class="w-16 h-16 bg-white rounded-2xl shadow-sm text-rose-500 flex justify-center items-center text-3xl mb-8 group-hover:bg-rose-500 group-hover:text-white transition-all duration-300">
                        <i class="fa-solid fa-file-invoice"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-4 tracking-tight">Automated Billing</h3>
                    <p class="text-gray-600 leading-relaxed">Generate professional invoices and receipts automatically upon order fulfillment or payment.</p>
                </div>
                
                <!-- Feature Card 6 -->
                <div class="bg-gray-50 rounded-3xl p-10 border border-gray-100 hover-lift fade-in-up delay-200 group">
                    <div class="w-16 h-16 bg-white rounded-2xl shadow-sm text-amber-500 flex justify-center items-center text-3xl mb-8 group-hover:bg-amber-500 group-hover:text-white transition-all duration-300">
                        <i class="fa-solid fa-mobile-screen-button"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-4 tracking-tight">Mobile Optimized</h3>
                    <p class="text-gray-600 leading-relaxed">Manage your warehouse on the go. Scan barcodes and update stock using any mobile device.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- How it Works (Process) -->
    <section id="how-it-works" class="py-32 bg-gray-900 text-white overflow-hidden relative">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="text-center max-w-3xl mx-auto mb-20 fade-in-up">
                <h2 class="text-4xl md:text-5xl font-extrabold mb-6">Streamlined Operations</h2>
                <p class="text-xl text-gray-400">Get up and running in minutes, not months. Our setup process is designed to be frictionless.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-8 relative">
                <!-- Connector Line (Desktop) -->
                <div class="hidden md:block absolute top-1/4 left-0 right-0 h-0.5 bg-gradient-to-r from-brand/50 to-secondary/50 z-0"></div>
                
                <!-- Step 1 -->
                <div class="relative z-10 text-center fade-in-up">
                    <div class="w-20 h-20 bg-brand text-white rounded-full flex items-center justify-center text-3xl font-bold mb-8 mx-auto shadow-2xl shadow-brand/20 border-4 border-gray-800">1</div>
                    <h3 class="text-2xl font-bold mb-4">Connect</h3>
                    <p class="text-gray-400">Import your existing product CSV or connect your storefront.</p>
                </div>

                <!-- Step 2 -->
                <div class="relative z-10 text-center fade-in-up delay-100">
                    <div class="w-20 h-20 bg-gray-800 text-white rounded-full flex items-center justify-center text-3xl font-bold mb-8 mx-auto shadow-2xl border-4 border-gray-800">2</div>
                    <h3 class="text-2xl font-bold mb-4">Configure</h3>
                    <p class="text-gray-400">Set low-stock thresholds and user permissions for your team.</p>
                </div>

                <!-- Step 3 -->
                <div class="relative z-10 text-center fade-in-up delay-200">
                    <div class="w-20 h-20 bg-gray-800 text-white rounded-full flex items-center justify-center text-3xl font-bold mb-8 mx-auto shadow-2xl border-4 border-gray-800">3</div>
                    <h3 class="text-2xl font-bold mb-4">Sync</h3>
                    <p class="text-gray-400">Real-time inventory sync begins across all your warehouses.</p>
                </div>

                <!-- Step 4 -->
                <div class="relative z-10 text-center fade-in-up delay-300">
                    <div class="w-20 h-20 bg-secondary text-white rounded-full flex items-center justify-center text-3xl font-bold mb-8 mx-auto shadow-2xl shadow-secondary/20 border-4 border-gray-800">4</div>
                    <h3 class="text-2xl font-bold mb-4">Optimize</h3>
                    <p class="text-gray-400">Receive smart reports and automate re-ordering processes.</p>
                </div>
            </div>
        </div>
        
        <!-- Background Decoration -->
        <div class="absolute top-0 right-0 -mr-40 -mt-40 w-96 h-96 bg-brand/30 rounded-full blur-3xl opacity-20"></div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="py-32 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-20 fade-in-up">
                <h2 class="text-4xl md:text-5xl font-extrabold text-gray-900 mb-6 font-sans">Simple, Transparent Pricing</h2>
                <p class="text-lg text-gray-600">Choose the plan that fits your business stage. No hidden fees, ever.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Basic Plan -->
                <div class="bg-white rounded-3xl p-10 border border-gray-200 shadow-sm hover-lift fade-in-up">
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Startup</h3>
                    <div class="flex items-baseline mb-8">
                        <span class="text-4xl font-extrabold text-gray-900">$29</span>
                        <span class="text-gray-500 ml-1">/mo</span>
                    </div>
                    <ul class="space-y-4 mb-10 text-gray-600">
                        <li class="flex items-center gap-3"><i class="fa-solid fa-circle-check text-secondary"></i> Up to 500 Products</li>
                        <li class="flex items-center gap-3"><i class="fa-solid fa-circle-check text-secondary"></i> 2 Warehouse Locations</li>
                        <li class="flex items-center gap-3"><i class="fa-solid fa-circle-check text-secondary"></i> Standard Analytics</li>
                        <li class="flex items-center gap-3"><i class="fa-solid fa-circle-check text-secondary"></i> 3 Team Users</li>
                    </ul>
                    <a href="auth/login.php" class="block w-full text-center py-4 rounded-xl border-2 border-brand text-brand font-bold hover:bg-brand hover:text-white transition-all">Start Free Trial</a>
                </div>

                <!-- Pro Plan -->
                <div class="bg-white rounded-3xl p-10 border-2 border-brand relative shadow-xl hover-lift fade-in-up delay-100 lg:scale-105 z-10">
                    <div class="absolute -top-4 left-1/2 -translate-x-1/2 bg-brand text-white px-4 py-1 rounded-full text-sm font-bold uppercase tracking-widest">Most Popular</div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Professional</h3>
                    <div class="flex items-baseline mb-8">
                        <span class="text-4xl font-extrabold text-gray-900">$79</span>
                        <span class="text-gray-500 ml-1">/mo</span>
                    </div>
                    <ul class="space-y-4 mb-10 text-gray-600">
                        <li class="flex items-center gap-3"><i class="fa-solid fa-circle-check text-secondary"></i> Unlimited Products</li>
                        <li class="flex items-center gap-3"><i class="fa-solid fa-circle-check text-secondary"></i> 10 Warehouse Locations</li>
                        <li class="flex items-center gap-3"><i class="fa-solid fa-circle-check text-secondary"></i> Advanced AI Forecasting</li>
                        <li class="flex items-center gap-3"><i class="fa-solid fa-circle-check text-secondary"></i> 15 Team Users</li>
                        <li class="flex items-center gap-3"><i class="fa-solid fa-circle-check text-secondary"></i> Priority Support</li>
                    </ul>
                    <a href="auth/login.php" class="block w-full text-center py-4 rounded-xl bg-brand text-white font-bold hover:bg-brand-dark transition-all shadow-lg shadow-brand/20">Get Started Now</a>
                </div>

                <!-- Enterprise Plan -->
                <div class="bg-white rounded-3xl p-10 border border-gray-200 shadow-sm hover-lift fade-in-up delay-200">
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Enterprise</h3>
                    <div class="flex items-baseline mb-8">
                        <span class="text-4xl font-extrabold text-gray-900">$199</span>
                        <span class="text-gray-500 ml-1">/mo</span>
                    </div>
                    <ul class="space-y-4 mb-10 text-gray-600">
                        <li class="flex items-center gap-3"><i class="fa-solid fa-circle-check text-secondary"></i> Custom White-label</li>
                        <li class="flex items-center gap-3"><i class="fa-solid fa-circle-check text-secondary"></i> Global Supply Chain Sync</li>
                        <li class="flex items-center gap-3"><i class="fa-solid fa-circle-check text-secondary"></i> Dedicated Data Expert</li>
                        <li class="flex items-center gap-3"><i class="fa-solid fa-circle-check text-secondary"></i> Unlimited Users</li>
                    </ul>
                    <a href="auth/login.php" class="block w-full text-center py-4 rounded-xl border-2 border-gray-200 text-gray-800 font-bold hover:bg-gray-800 hover:text-white hover:border-gray-800 transition-all">Contact Sales</a>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="py-32 bg-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16 fade-in-up">
                <h2 class="text-4xl font-extrabold text-gray-900 mb-4">Frequently Asked Questions</h2>
                <p class="text-lg text-gray-600">Everything you need to know about starting with Inventory Pro.</p>
            </div>
            
            <div class="space-y-4">
                <details class="group bg-gray-50 rounded-2xl p-6 border border-gray-100 transition-all duration-300">
                    <summary class="flex justify-between items-center font-bold text-lg text-gray-900 cursor-pointer list-none">
                        Can I migrate data from Excel or other systems?
                        <span class="transition group-open:rotate-180 bg-white p-2 rounded-lg shadow-sm">
                            <i class="fa-solid fa-chevron-down text-sm"></i>
                        </span>
                    </summary>
                    <div class="mt-4 text-gray-600 leading-relaxed">
                        Yes! You can import all your products, customers, and distributors using our simple CSV import tool. We also offer direct integration with major e-commerce platforms like Shopify and WooCommerce.
                    </div>
                </details>

                <details class="group bg-gray-50 rounded-2xl p-6 border border-gray-100 transition-all duration-300">
                    <summary class="flex justify-between items-center font-bold text-lg text-gray-900 cursor-pointer list-none">
                        Is my data secure and backed up?
                        <span class="transition group-open:rotate-180 bg-white p-2 rounded-lg shadow-sm">
                            <i class="fa-solid fa-chevron-down text-sm"></i>
                        </span>
                    </summary>
                    <div class="mt-4 text-gray-600 leading-relaxed">
                        Absolutely. We use enterprise-grade encryption for all data storage and transmission. Daily automated backups ensure that your business records are always safe and recoverable.
                    </div>
                </details>

                <details class="group bg-gray-50 rounded-2xl p-6 border border-gray-100 transition-all duration-300">
                    <summary class="flex justify-between items-center font-bold text-lg text-gray-900 cursor-pointer list-none">
                        Does it support multiple warehouses?
                        <span class="transition group-open:rotate-180 bg-white p-2 rounded-lg shadow-sm">
                            <i class="fa-solid fa-chevron-down text-sm"></i>
                        </span>
                    </summary>
                    <div class="mt-4 text-gray-600 leading-relaxed">
                        Yes, our system is built for multi-warehouse management. You can track stock across different physical locations, transfer items between them, and see aggregated totals in real-time.
                    </div>
                </details>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="py-32 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-[3rem] shadow-2xl overflow-hidden border border-gray-100 fade-in-up">
                <div class="grid grid-cols-1 lg:grid-cols-2">
                    <!-- Contact Info Card -->
                    <div class="bg-brand p-12 lg:p-16 text-white relative h-full">
                        <div class="absolute top-0 right-0 p-16 opacity-10">
                            <i class="fa-solid fa-paper-plane text-[15rem] rotate-12"></i>
                        </div>
                        
                        <div class="relative z-10">
                            <h2 class="text-4xl font-extrabold mb-8">Let's Talk Logistics</h2>
                            <p class="text-blue-100 text-lg mb-12">Have a unique business model or need custom integrations? Our team of experts is ready to help you scale your output.</p>
                            
                            <div class="space-y-8">
                                <div class="flex items-start gap-5">
                                    <div class="w-12 h-12 bg-white/10 rounded-xl flex items-center justify-center text-xl shrink-0"><i class="fa-solid fa-location-dot"></i></div>
                                    <div>
                                        <h4 class="font-bold text-xl mb-1">Visit Our HQ</h4>
                                        <p class="text-blue-100">456 Logistics Drive, Central Port, NY 10012</p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-5">
                                    <div class="w-12 h-12 bg-white/10 rounded-xl flex items-center justify-center text-xl shrink-0"><i class="fa-solid fa-phone"></i></div>
                                    <div>
                                        <h4 class="font-bold text-xl mb-1">Call Us</h4>
                                        <p class="text-blue-100">+1 (888) STOCK-PRO</p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-5">
                                    <div class="w-12 h-12 bg-white/10 rounded-xl flex items-center justify-center text-xl shrink-0"><i class="fa-solid fa-envelope"></i></div>
                                    <div>
                                        <h4 class="font-bold text-xl mb-1">Email Support</h4>
                                        <p class="text-blue-100">hello@inventorypro.com</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Form -->
                    <div class="p-12 lg:p-16">
                        <form id="landingContactForm" onsubmit="event.preventDefault(); alert('Message sent successfully!'); this.reset();" class="space-y-6">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-2">First Name</label>
                                    <input type="text" class="w-full px-5 py-4 bg-gray-50 border border-gray-200 rounded-2xl focus:ring-2 focus:ring-brand focus:border-brand outline-none transition-all" placeholder="John">
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-2">Last Name</label>
                                    <input type="text" class="w-full px-5 py-4 bg-gray-50 border border-gray-200 rounded-2xl focus:ring-2 focus:ring-brand focus:border-brand outline-none transition-all" placeholder="Doe">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Work Email</label>
                                <input type="email" class="w-full px-5 py-4 bg-gray-50 border border-gray-200 rounded-2xl focus:ring-2 focus:ring-brand focus:border-brand outline-none transition-all" placeholder="john@company.com">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">How can we help?</label>
                                <textarea rows="5" class="w-full px-5 py-4 bg-gray-50 border border-gray-200 rounded-2xl focus:ring-2 focus:ring-brand focus:border-brand outline-none transition-all resize-none" placeholder="Tell us about your inventory needs..."></textarea>
                            </div>
                            <button type="submit" class="w-full py-5 bg-brand text-white font-bold rounded-2xl shadow-xl shadow-brand/20 hover:bg-brand-dark transition-all flex items-center justify-center gap-3">
                                Send Message <i class="fa-solid fa-paper-plane"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer Section -->
    <footer class="bg-gray-900 text-gray-300 py-20 border-t border-gray-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-16 mb-16">
                
                <!-- Brand Info -->
                <div class="md:col-span-1">
                    <a href="#" class="flex items-center gap-2 mb-8">
                        <div class="w-8 h-8 bg-brand text-white rounded flex items-center justify-center text-sm shadow">
                            <i class="fa-solid fa-boxes-stacked"></i>
                        </div>
                        <span class="font-bold text-2xl text-white tracking-tight">Inventory<span class="text-brand">Pro</span></span>
                    </a>
                    <p class="text-gray-400 text-sm leading-relaxed mb-8">Next-generation logistics management for high-growth businesses. We optimize the world's inventory, one warehouse at a time.</p>
                </div>

                <!-- Product -->
                <div>
                    <h4 class="text-white font-bold text-lg mb-8">Product</h4>
                    <ul class="space-y-4 text-sm">
                        <li><a href="#features" class="hover:text-brand transition-colors">Features</a></li>
                        <li><a href="#how-it-works" class="hover:text-brand transition-colors">Integrations</a></li>
                        <li><a href="#pricing" class="hover:text-brand transition-colors">Pricing Plans</a></li>
                    </ul>
                </div>

                <!-- Company -->
                <div>
                    <h4 class="text-white font-bold text-lg mb-8">Company</h4>
                    <ul class="space-y-4 text-sm">
                        <li><a href="#about" class="hover:text-brand transition-colors">About Us</a></li>
                        <li><a href="#" class="hover:text-brand transition-colors">Privacy Policy</a></li>
                    </ul>
                </div>

                <!-- Contact -->
                <div>
                    <h4 class="text-white font-bold text-lg mb-8">Contact</h4>
                    <ul class="space-y-4 text-sm">
                        <li class="flex items-start gap-3">
                            <i class="fa-solid fa-location-dot mt-1 text-brand"></i>
                            <span class="text-gray-400">456 Logistics Drive, NY 10012</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Bottom Footer -->
            <div class="pt-10 border-t border-gray-800 text-center">
                <p class="text-sm text-gray-500">&copy; <?= date('Y') ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script>
        // Mobile menu toggle
        document.getElementById('mobile-menu-button').addEventListener('click', function() {
            var menu = document.getElementById('mobile-menu');
            menu.classList.toggle('hidden');
        });

        // Intersection Observer for scroll animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        }, observerOptions);

        document.querySelectorAll('.fade-in-up').forEach((el) => {
            observer.observe(el);
        });
    </script>
</body>
</html>
