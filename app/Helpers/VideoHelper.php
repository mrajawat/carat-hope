<?php

namespace App\Helpers;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class VideoHelper
{
    /**
     * Upload a video (either base64 string or UploadedFile) and return URL.
     *
     * @param mixed $videoInput
     * @param string $folder
     * @return string|null
     * @throws \Exception
     */
    public static function upload($videoInput, $folder = 'videos')
    {
        if ($videoInput instanceof \Illuminate\Http\UploadedFile) {
            $path = $videoInput->store($folder, 'public');
            return Storage::disk('public')->url($path);
        }

        if (is_string($videoInput)) {
            return self::uploadBase64($videoInput, $folder);
        }

        return null;
    }

    /**
     * Upload a base64 encoded video or return existing URL as-is.
     *
     * @param string $base64String
     * @param string $folder
     * @return string
     * @throws \Exception
     */
    public static function uploadBase64($base64String, $folder = 'videos')
    {
        if (preg_match('/^data:video\/(\w+);base64,/', $base64String, $type)) {
            // Grab base64 data and extension
            $data = substr($base64String, strpos($base64String, ',') + 1);
            $ext = strtolower($type[1]); // mp4, webm, ogg, quicktime, etc.
            
            if (!in_array($ext, ['mp4', 'webm', 'ogg', 'mov', 'quicktime'])) {
                throw new \Exception('Invalid video type.');
            }
            
            // Normalize quicktime extension
            if ($ext === 'quicktime') {
                $ext = 'mov';
            }
            
            $data = base64_decode($data);
            if ($data === false) {
                throw new \Exception('Base64 decode failed.');
            }
            
            // Generate unique filename
            $filename = Str::random(20) . '.' . $ext;
            $path = "$folder/$filename";

            Storage::disk('public')->put($path, $data);
            
            // Return public URL path
            return Storage::disk('public')->url($path);
        }
        
        // If it is already a URL string, return it as-is
        return $base64String;
    }
}
