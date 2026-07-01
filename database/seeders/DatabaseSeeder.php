<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Coupon;
use App\Models\User;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Seed Admins
        Admin::create([
            'name' => 'Super Admin',
            'email' => 'admin@caratehope.com',
            'password' => 'password123', // Admin model casts this to hashed
            'role' => 'super_admin',
            'avatar' => 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&q=80&w=150',
        ]);

        Admin::create([
            'name' => 'John Doe',
            'email' => 'john@caratehope.com',
            'password' => 'password123',
            'role' => 'admin',
            'avatar' => 'https://images.unsplash.com/photo-1570295999919-56ceb5ecca61?auto=format&fit=crop&q=80&w=150',
        ]);

        // 2. Seed Banners
        Banner::create([
            'image' => 'https://images.unsplash.com/photo-1515562141207-7a88fb7ce338?auto=format&fit=crop&q=80&w=1920',
            'badge' => 'FESTIVE COLLECTION',
            'title' => 'Elegance Redefined',
            'description' => 'Discover our handcrafted diamond rings and precious gemstones with 20% off.',
            'status' => 'active',
        ]);

        Banner::create([
            'image' => 'https://images.unsplash.com/photo-1601121141461-9d6647bca1ed?auto=format&fit=crop&q=80&w=1920',
            'badge' => 'NEW IN STORE',
            'title' => 'The Gold Standard',
            'description' => 'Timeless 22K yellow gold necklaces designed for modern royalty.',
            'status' => 'active',
        ]);

        // 3. Seed Categories
        $rings = Category::create([
            'name' => 'Rings',
            'slug' => 'rings',
            'image' => 'https://images.unsplash.com/photo-1605100804763-247f67b3557e?auto=format&fit=crop&q=80&w=500',
            'description' => 'Exquisite diamond, gold, and platinum rings for engagement and everyday elegance.',
            'status' => 'active',
        ]);

        $necklaces = Category::create([
            'name' => 'Necklaces',
            'slug' => 'necklaces',
            'image' => 'https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?auto=format&fit=crop&q=80&w=500',
            'description' => 'Stunning necklaces, chokers, and chains featuring fine gemstones and pearls.',
            'status' => 'active',
        ]);

        $earrings = Category::create([
            'name' => 'Earrings',
            'slug' => 'earrings',
            'image' => 'https://images.unsplash.com/photo-1535632066927-ab7c9ab60908?auto=format&fit=crop&q=80&w=500',
            'description' => 'Studs, hoops, and drop earrings crafted to make you shine.',
            'status' => 'active',
        ]);

        $bracelets = Category::create([
            'name' => 'Bracelets',
            'slug' => 'bracelets',
            'image' => 'https://images.unsplash.com/photo-1611591437281-460bfbe1220a?auto=format&fit=crop&q=80&w=500',
            'description' => 'Elegant bangles, charm bracelets, and tennis cuffs.',
            'status' => 'active',
        ]);

        // 4. Seed Products
        $p1 = Product::create([
            'name' => 'Eternal Diamond Solitaire Ring',
            'sku' => 'RNG-SOL-001',
            'category_id' => $rings->id,
            'price' => 75000.00,
            'discount_price' => 69999.00,
            'stock_qty' => 15,
            'description' => 'A timeless classic featuring a 1-carat round brilliant cut diamond on a 18K white gold band.',
            'is_featured' => true,
            'status' => 'active',
        ]);

        ProductImage::create([
            'product_id' => $p1->id,
            'image_path' => 'https://images.unsplash.com/photo-1605100804763-247f67b3557e?auto=format&fit=crop&q=80&w=600',
            'is_primary' => true,
        ]);

        $p2 = Product::create([
            'name' => 'Royal Emerald Pendant Necklace',
            'sku' => 'NEC-EMR-002',
            'category_id' => $necklaces->id,
            'price' => 120000.00,
            'discount_price' => 105000.00,
            'stock_qty' => 5,
            'description' => 'An opulent oval-cut Zambian emerald surrounded by micro-paved diamonds on a 22K yellow gold chain.',
            'is_featured' => true,
            'status' => 'active',
        ]);

        ProductImage::create([
            'product_id' => $p2->id,
            'image_path' => 'https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?auto=format&fit=crop&q=80&w=600',
            'is_primary' => true,
        ]);

        $p3 = Product::create([
            'name' => 'Classic Diamond Hoop Earrings',
            'sku' => 'EAR-HOP-003',
            'category_id' => $earrings->id,
            'price' => 45000.00,
            'discount_price' => null,
            'stock_qty' => 20,
            'description' => 'Elegant and versatile hoop earrings lined with sparkling round-cut diamonds.',
            'is_featured' => false,
            'status' => 'active',
        ]);

        ProductImage::create([
            'product_id' => $p3->id,
            'image_path' => 'https://images.unsplash.com/photo-1535632066927-ab7c9ab60908?auto=format&fit=crop&q=80&w=600',
            'is_primary' => true,
        ]);

        $p4 = Product::create([
            'name' => 'Infinite Love Rose Gold Bracelet',
            'sku' => 'BRC-INF-004',
            'category_id' => $bracelets->id,
            'price' => 35000.00,
            'discount_price' => 29999.00,
            'stock_qty' => 8,
            'description' => 'Delicate 18K rose gold chain bracelet with an infinity symbol set with shimmering diamonds.',
            'is_featured' => false,
            'status' => 'active',
        ]);

        ProductImage::create([
            'product_id' => $p4->id,
            'image_path' => 'https://images.unsplash.com/photo-1611591437281-460bfbe1220a?auto=format&fit=crop&q=80&w=600',
            'is_primary' => true,
        ]);

        $p5 = Product::create([
            'name' => 'Vintage Platinum Sapphire Ring',
            'sku' => 'RNG-VNT-005',
            'category_id' => $rings->id,
            'price' => 85000.00,
            'discount_price' => 79999.00,
            'stock_qty' => 10,
            'description' => 'A breathtaking vintage-inspired platinum ring featuring a deep blue sapphire haloed by diamonds.',
            'is_featured' => true,
            'status' => 'active',
        ]);
        ProductImage::create([
            'product_id' => $p5->id,
            'image_path' => 'https://images.unsplash.com/photo-1605100804763-247f67b3557e?auto=format&fit=crop&q=80&w=600',
            'is_primary' => true,
        ]);

        $p6 = Product::create([
            'name' => 'Rose Gold Pearl Choker',
            'sku' => 'NEC-PRL-006',
            'category_id' => $necklaces->id,
            'price' => 55000.00,
            'discount_price' => null,
            'stock_qty' => 12,
            'description' => 'An elegant choker made of cultured pearls and 18K rose gold accents.',
            'is_featured' => false,
            'status' => 'active',
        ]);
        ProductImage::create([
            'product_id' => $p6->id,
            'image_path' => 'https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?auto=format&fit=crop&q=80&w=600',
            'is_primary' => true,
        ]);

        $p7 = Product::create([
            'name' => 'Gold Drop Chandelier Earrings',
            'sku' => 'EAR-DRP-007',
            'category_id' => $earrings->id,
            'price' => 62000.00,
            'discount_price' => 58000.00,
            'stock_qty' => 7,
            'description' => 'Exquisite chandelier earrings crafted in 22K yellow gold.',
            'is_featured' => true,
            'status' => 'active',
        ]);
        ProductImage::create([
            'product_id' => $p7->id,
            'image_path' => 'https://images.unsplash.com/photo-1535632066927-ab7c9ab60908?auto=format&fit=crop&q=80&w=600',
            'is_primary' => true,
        ]);

        $p8 = Product::create([
            'name' => 'Diamond Tennis Bracelet',
            'sku' => 'BRC-TNS-008',
            'category_id' => $bracelets->id,
            'price' => 150000.00,
            'discount_price' => 140000.00,
            'stock_qty' => 4,
            'description' => 'A luxurious tennis bracelet featuring a continuous line of sparkling diamonds.',
            'is_featured' => true,
            'status' => 'active',
        ]);
        ProductImage::create([
            'product_id' => $p8->id,
            'image_path' => 'https://images.unsplash.com/photo-1611591437281-460bfbe1220a?auto=format&fit=crop&q=80&w=600',
            'is_primary' => true,
        ]);

        $p9 = Product::create([
            'name' => 'Ruby and Gold Engagement Ring',
            'sku' => 'RNG-RBY-009',
            'category_id' => $rings->id,
            'price' => 95000.00,
            'discount_price' => 89000.00,
            'stock_qty' => 6,
            'description' => 'A striking ruby surrounded by delicate diamonds on a 18K yellow gold band.',
            'is_featured' => true,
            'status' => 'active',
        ]);
        ProductImage::create([
            'product_id' => $p9->id,
            'image_path' => 'https://images.unsplash.com/photo-1605100804763-247f67b3557e?auto=format&fit=crop&q=80&w=600',
            'is_primary' => true,
        ]);

        $p10 = Product::create([
            'name' => 'Minimalist Gold Chain',
            'sku' => 'NEC-MIN-010',
            'category_id' => $necklaces->id,
            'price' => 25000.00,
            'discount_price' => null,
            'stock_qty' => 30,
            'description' => 'A simple, minimalist 18K gold chain perfect for everyday wear.',
            'is_featured' => false,
            'status' => 'active',
        ]);
        ProductImage::create([
            'product_id' => $p10->id,
            'image_path' => 'https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?auto=format&fit=crop&q=80&w=600',
            'is_primary' => true,
        ]);

        $p11 = Product::create([
            'name' => 'Sapphire Stud Earrings',
            'sku' => 'EAR-STD-011',
            'category_id' => $earrings->id,
            'price' => 38000.00,
            'discount_price' => 35000.00,
            'stock_qty' => 18,
            'description' => 'Beautiful deep blue sapphire stud earrings set in 18K white gold.',
            'is_featured' => false,
            'status' => 'active',
        ]);
        ProductImage::create([
            'product_id' => $p11->id,
            'image_path' => 'https://images.unsplash.com/photo-1535632066927-ab7c9ab60908?auto=format&fit=crop&q=80&w=600',
            'is_primary' => true,
        ]);

        $p12 = Product::create([
            'name' => 'Silver Charm Bracelet',
            'sku' => 'BRC-CHM-012',
            'category_id' => $bracelets->id,
            'price' => 15000.00,
            'discount_price' => 12999.00,
            'stock_qty' => 25,
            'description' => 'A playful sterling silver charm bracelet with assorted beautiful charms.',
            'is_featured' => false,
            'status' => 'active',
        ]);
        ProductImage::create([
            'product_id' => $p12->id,
            'image_path' => 'https://images.unsplash.com/photo-1611591437281-460bfbe1220a?auto=format&fit=crop&q=80&w=600',
            'is_primary' => true,
        ]);

        $p13 = Product::create([
            'name' => 'Emerald Cut Diamond Ring',
            'sku' => 'RNG-EMC-013',
            'category_id' => $rings->id,
            'price' => 110000.00,
            'discount_price' => null,
            'stock_qty' => 3,
            'description' => 'A stunning 2-carat emerald cut diamond set in a platinum band.',
            'is_featured' => true,
            'status' => 'active',
        ]);
        ProductImage::create([
            'product_id' => $p13->id,
            'image_path' => 'https://images.unsplash.com/photo-1605100804763-247f67b3557e?auto=format&fit=crop&q=80&w=600',
            'is_primary' => true,
        ]);

        $p14 = Product::create([
            'name' => 'Gold Statement Necklace',
            'sku' => 'NEC-STT-014',
            'category_id' => $necklaces->id,
            'price' => 200000.00,
            'discount_price' => 185000.00,
            'stock_qty' => 2,
            'description' => 'An elaborate, handcrafted 22K gold statement necklace for special occasions.',
            'is_featured' => true,
            'status' => 'active',
        ]);
        ProductImage::create([
            'product_id' => $p14->id,
            'image_path' => 'https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?auto=format&fit=crop&q=80&w=600',
            'is_primary' => true,
        ]);

        // 5. Seed Coupons
        Coupon::create([
            'code' => 'GOLD25',
            'discount_type' => 'percentage',
            'discount_value' => 25.00,
            'expiry_date' => Carbon::now()->addMonths(3),
            'usage_limit' => 100,
            'usage_count' => 12,
            'status' => 'active',
        ]);

        Coupon::create([
            'code' => 'WELCOME500',
            'discount_type' => 'fixed',
            'discount_value' => 500.00,
            'expiry_date' => Carbon::now()->addMonths(1),
            'usage_limit' => 500,
            'usage_count' => 35,
            'status' => 'active',
        ]);

        Coupon::create([
            'code' => 'EXPIRED10',
            'discount_type' => 'percentage',
            'discount_value' => 10.00,
            'expiry_date' => Carbon::now()->subDays(5),
            'usage_limit' => 50,
            'usage_count' => 50,
            'status' => 'inactive',
        ]);

        // 6. Seed App Registered Customers
        $u1 = User::create([
            'name' => 'Rahul Sharma',
            'email' => 'rahul@example.com',
            'password' => 'customer123',
            'phone' => '+919876543210',
            'avatar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&q=80&w=100',
            'status' => 'active',
        ]);

        $u2 = User::create([
            'name' => 'Priya Patel',
            'email' => 'priya@example.com',
            'password' => 'customer123',
            'phone' => '+919988776655',
            'avatar' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&q=80&w=100',
            'status' => 'active',
        ]);

        $u3 = User::create([
            'name' => 'Amit Kumar',
            'email' => 'amit@example.com',
            'password' => 'customer123',
            'phone' => '+918877665544',
            'avatar' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&q=80&w=100',
            'status' => 'active',
        ]);

        // 7. Seed Orders & OrderItems to populate Dashboard Charts
        // We will seed orders spanning the last 6 months to make the chart look nice
        $now = Carbon::now();

        // 5 Months Ago
        $o1 = Order::create([
            'user_id' => $u1->id,
            'customer_name' => $u1->name,
            'customer_email' => $u1->email,
            'customer_phone' => $u1->phone,
            'total_amount' => 69999.00,
            'payment_status' => 'paid',
            'order_status' => 'delivered',
            'created_at' => $now->copy()->subMonths(5)->subDays(10),
        ]);
        OrderItem::create([
            'order_id' => $o1->id,
            'product_id' => $p1->id,
            'product_name' => $p1->name,
            'product_image' => 'https://images.unsplash.com/photo-1605100804763-247f67b3557e?auto=format&fit=crop&q=80&w=600',
            'quantity' => 1,
            'price' => 69999.00,
        ]);

        // 4 Months Ago
        $o2 = Order::create([
            'user_id' => $u2->id,
            'customer_name' => $u2->name,
            'customer_email' => $u2->email,
            'customer_phone' => $u2->phone,
            'total_amount' => 105000.00,
            'payment_status' => 'paid',
            'order_status' => 'delivered',
            'created_at' => $now->copy()->subMonths(4)->subDays(15),
        ]);
        OrderItem::create([
            'order_id' => $o2->id,
            'product_id' => $p2->id,
            'product_name' => $p2->name,
            'product_image' => 'https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?auto=format&fit=crop&q=80&w=600',
            'quantity' => 1,
            'price' => 105000.00,
        ]);

        // 3 Months Ago
        $o3 = Order::create([
            'user_id' => $u3->id,
            'customer_name' => $u3->name,
            'customer_email' => $u3->email,
            'customer_phone' => $u3->phone,
            'total_amount' => 29999.00,
            'payment_status' => 'paid',
            'order_status' => 'delivered',
            'created_at' => $now->copy()->subMonths(3)->subDays(5),
        ]);
        OrderItem::create([
            'order_id' => $o3->id,
            'product_id' => $p4->id,
            'product_name' => $p4->name,
            'product_image' => 'https://images.unsplash.com/photo-1611591437281-460bfbe1220a?auto=format&fit=crop&q=80&w=600',
            'quantity' => 1,
            'price' => 29999.00,
        ]);

        // 2 Months Ago (2 orders)
        $o4 = Order::create([
            'user_id' => $u1->id,
            'customer_name' => $u1->name,
            'customer_email' => $u1->email,
            'customer_phone' => $u1->phone,
            'total_amount' => 45000.00,
            'payment_status' => 'paid',
            'order_status' => 'shipped',
            'created_at' => $now->copy()->subMonths(2)->subDays(12),
        ]);
        OrderItem::create([
            'order_id' => $o4->id,
            'product_id' => $p3->id,
            'product_name' => $p3->name,
            'product_image' => 'https://images.unsplash.com/photo-1535632066927-ab7c9ab60908?auto=format&fit=crop&q=80&w=600',
            'quantity' => 1,
            'price' => 45000.00,
        ]);

        $o5 = Order::create([
            'user_id' => $u2->id,
            'customer_name' => $u2->name,
            'customer_email' => $u2->email,
            'customer_phone' => $u2->phone,
            'total_amount' => 99998.00,
            'payment_status' => 'paid',
            'order_status' => 'delivered',
            'created_at' => $now->copy()->subMonths(2)->subDays(2),
        ]);
        OrderItem::create([
            'order_id' => $o5->id,
            'product_id' => $p4->id,
            'product_name' => $p4->name,
            'product_image' => 'https://images.unsplash.com/photo-1611591437281-460bfbe1220a?auto=format&fit=crop&q=80&w=600',
            'quantity' => 2,
            'price' => 29999.00,
        ]);
        OrderItem::create([
            'order_id' => $o5->id,
            'product_id' => $p3->id,
            'product_name' => $p3->name,
            'product_image' => 'https://images.unsplash.com/photo-1535632066927-ab7c9ab60908?auto=format&fit=crop&q=80&w=600',
            'quantity' => 1,
            'price' => 40000.00,
        ]);

        // 1 Month Ago
        $o6 = Order::create([
            'user_id' => $u3->id,
            'customer_name' => $u3->name,
            'customer_email' => $u3->email,
            'customer_phone' => $u3->phone,
            'total_amount' => 139998.00,
            'payment_status' => 'paid',
            'order_status' => 'delivered',
            'created_at' => $now->copy()->subMonth()->subDays(8),
        ]);
        OrderItem::create([
            'order_id' => $o6->id,
            'product_id' => $p1->id,
            'product_name' => $p1->name,
            'product_image' => 'https://images.unsplash.com/photo-1605100804763-247f67b3557e?auto=format&fit=crop&q=80&w=600',
            'quantity' => 2,
            'price' => 69999.00,
        ]);

        // Current Month (2 orders)
        $o7 = Order::create([
            'user_id' => $u1->id,
            'customer_name' => $u1->name,
            'customer_email' => $u1->email,
            'customer_phone' => $u1->phone,
            'total_amount' => 105000.00,
            'payment_status' => 'unpaid',
            'order_status' => 'pending',
            'created_at' => $now->copy()->subDays(4),
        ]);
        OrderItem::create([
            'order_id' => $o7->id,
            'product_id' => $p2->id,
            'product_name' => $p2->name,
            'product_image' => 'https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?auto=format&fit=crop&q=80&w=600',
            'quantity' => 1,
            'price' => 105000.00,
        ]);

        $o8 = Order::create([
            'user_id' => $u2->id,
            'customer_name' => $u2->name,
            'customer_email' => $u2->email,
            'customer_phone' => $u2->phone,
            'total_amount' => 69999.00,
            'payment_status' => 'paid',
            'order_status' => 'processing',
            'created_at' => $now->copy()->subDays(1),
        ]);
        OrderItem::create([
            'order_id' => $o8->id,
            'product_id' => $p1->id,
            'product_name' => $p1->name,
            'product_image' => 'https://images.unsplash.com/photo-1605100804763-247f67b3557e?auto=format&fit=crop&q=80&w=600',
            'quantity' => 1,
            'price' => 69999.00,
        ]);
    }
}
