<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Banner;
use App\Models\Category;
use App\Models\ProductImage;
use Illuminate\Support\Facades\Storage;

class OptimizeImages extends Command
{
    protected $signature = 'images:optimize';
    protected $description = 'Optimize existing images by converting them to WebP and compressing them';

    public function handle()
    {
        $this->info('Starting image optimization...');

        // 1. Optimize Banners
        $this->optimizeTableImages(Banner::class, 'image', 'banners', 1600);

        // 2. Optimize Categories
        $this->optimizeTableImages(Category::class, 'image', 'categories', 500);

        // 3. Optimize Product Images
        $this->optimizeTableImages(ProductImage::class, 'image_path', 'products', 800);

        $this->info('Image optimization completed!');
    }

    protected function optimizeTableImages($modelClass, $columnName, $folder, $maxWidth)
    {
        $this->info("Optimizing {$modelClass} images...");
        $records = $modelClass::all();

        foreach ($records as $record) {
            $url = $record->$columnName;

            // Skip external or non-local images
            if (empty($url) || strpos($url, 'unsplash.com') !== false) {
                continue;
            }

            // Extract relative path from URL
            $parsedUrl = parse_url($url);
            $pathInfo = $parsedUrl['path'] ?? '';
            
            // Handle different patterns like /storage/banners/abc.png or storage/banners/abc.png
            $relativePath = '';
            if (preg_match('/storage\/(.*)$/', $pathInfo, $matches)) {
                $relativePath = $matches[1];
            } else {
                continue;
            }

            if (!Storage::disk('public')->exists($relativePath)) {
                $this->warn("File does not exist: {$relativePath}");
                continue;
            }

            $fullPath = Storage::disk('public')->path($relativePath);
            $oldSize = filesize($fullPath);

            // Read the image
            $imageData = file_get_contents($fullPath);
            $image = @imagecreatefromstring($imageData);
            if (!$image) {
                $this->error("Failed to load image: {$relativePath}");
                continue;
            }

            // Dimensions
            $width = imagesx($image);
            $height = imagesy($image);

            // Resize if needed
            if ($width > $maxWidth) {
                $newWidth = $maxWidth;
                $newHeight = (int)($height * ($maxWidth / $width));
                
                $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
                imagealphablending($resizedImage, false);
                imagesavealpha($resizedImage, true);
                
                imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                imagedestroy($image);
                $image = $resizedImage;
            }

            // Save to WebP
            $dirName = dirname($relativePath);
            $baseName = pathinfo($relativePath, PATHINFO_FILENAME);
            $newRelativePath = ($dirName === '.' ? '' : $dirName . '/') . $baseName . '.webp';
            $newFullPath = Storage::disk('public')->path($newRelativePath);

            // Output webp format
            imagewebp($image, $newFullPath, 75);
            imagedestroy($image);

            $newSize = filesize($newFullPath);
            $savings = round(($oldSize - $newSize) / 1024, 2);

            $this->info("Optimized: {$relativePath} -> {$newRelativePath} (Saved {$savings} KB)");

            // Delete old file if the path/extension changed
            if ($newRelativePath !== $relativePath) {
                Storage::disk('public')->delete($relativePath);
            }

            // Update database record
            $newUrl = Storage::disk('public')->url($newRelativePath);
            $record->$columnName = $newUrl;
            $record->save();
        }
    }
}
