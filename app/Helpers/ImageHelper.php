<?php

namespace App\Helpers;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class ImageHelper
{
    public static function uploadBase64($base64String, $folder = 'uploads')
    {
         if ($base64String instanceof \Illuminate\Http\UploadedFile) {
            $data = file_get_contents($base64String->getRealPath());
            $ext = strtolower($base64String->getClientOriginalExtension());
            
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'])) {
                throw new \Exception('Invalid image type.');
            }
            
            $filename = Str::random(20) . '.webp';
            $path = "$folder/$filename";

            $optimizedData = self::optimizeAndSave($data, $folder);
            if ($optimizedData) {
                Storage::disk('public')->put($path, $optimizedData);
            } else {
                $originalFilename = Str::random(20) . '.' . $ext;
                $path = "$folder/$originalFilename";
                Storage::disk('public')->put($path, $data);
            }
            
            return Storage::disk('public')->url($path);
        }
        
        if (preg_match('/^data:image\/(\w+);base64,/', $base64String, $type)) {
            // Grab base64 data and extension
            $data = substr($base64String, strpos($base64String, ',') + 1);
            $ext = strtolower($type[1]); // png, jpg, jpeg, gif
            
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'])) {
                throw new \Exception('Invalid image type.');
            }
            
            $data = base64_decode($data);
            if ($data === false) {
                throw new \Exception('Base64 decode failed.');
            }
            
            // Generate filename with .webp extension
            $filename = Str::random(20) . '.webp';
            $path = "$folder/$filename";

            // Optimize and convert to WebP
            $optimizedData = self::optimizeAndSave($data, $folder);
            if ($optimizedData) {
                Storage::disk('public')->put($path, $optimizedData);
            } else {
                // Fallback to original format if GD optimization fails
                $originalFilename = Str::random(20) . '.' . $ext;
                $path = "$folder/$originalFilename";
                Storage::disk('public')->put($path, $data);
            }
            
            // Return public URL path
            return Storage::disk('public')->url($path);
        }
        
        // If it is already a URL string, return it as-is
        return $base64String;
    }

    public static function optimizeAndSave($data, $folder)
    {
        // Load image from binary string
        $image = @imagecreatefromstring($data);
        if (!$image) {
            return false;
        }

        // Get dimensions
        $width = imagesx($image);
        $height = imagesy($image);

        // Determine target max width
        $maxWidth = 1000; // default
        if ($folder === 'banners') {
            $maxWidth = 1600;
        } elseif ($folder === 'products') {
            $maxWidth = 800;
        } elseif ($folder === 'categories') {
            $maxWidth = 500;
        }

        // Resize if larger than max width
        if ($width > $maxWidth) {
            $newWidth = $maxWidth;
            $newHeight = (int)($height * ($maxWidth / $width));
            
            $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
            
            // Preserve transparency for PNG/WebP
            imagealphablending($resizedImage, false);
            imagesavealpha($resizedImage, true);
            
            imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($image);
            $image = $resizedImage;
        }

        // Save as WebP
        ob_start();
        imagewebp($image, null, 75); // quality 75
        $webpData = ob_get_clean();
        
        imagedestroy($image);
        
        return $webpData;
    }
}
