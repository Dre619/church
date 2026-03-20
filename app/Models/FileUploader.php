<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Support\Facades\Session;

class FileUploader extends Model
{
    use HasFactory;

    public static function upload($uploaded_file, $upload_to, $width = null, $height = null, $optimized_width = 250, $optimized_height = null, $custom_name = null)
{
    if (!$uploaded_file) return null;

    // Check required extensions
    if (!extension_loaded('fileinfo')) {
        Session::flash('error', 'Please enable the fileinfo extension on your server.');
        return null;
    }

    if (!extension_loaded('exif')) {
        Session::flash('error', 'Please enable the exif extension on your server.');
        return null;
    }

    // Ensure the directory exists
    $absolute_path = public_path($upload_to);
    if (!is_dir($absolute_path)) {
        mkdir($absolute_path, 0777, true);
    }

    // Generate filename
    $file_name = $custom_name ?? (time() . '-' . uniqid() . '.' . $uploaded_file->extension());
    $file_path = $absolute_path . '/' . $file_name;

    if (!$width) {
        // Move file to public folder
        $uploaded_file->move($absolute_path, $file_name);
    } else {
        // Initialize ImageManager (v3 compatible)
        $manager = new ImageManager(new Driver()); // Ensure GD driver is specified

        // Read and resize image
        $image = $manager->read($uploaded_file);
            // Use resize with aspect ratio maintenance
            $image->resize($width, $height, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });

        // Save resized image
        $image->save($file_path);

        // Create optimized version
        if ($optimized_width) {
            $optimized_image = $manager->read($file_path)
                ->scale(width: $optimized_width, height: $optimized_height);

            $optimized_path = public_path($upload_to . '/optimized');
            if (!is_dir($optimized_path)) {
                mkdir($optimized_path, 0777, true);
            }

            $optimized_image->save($optimized_path . '/' . $file_name);
        }
    }

    return asset($upload_to . '/' . $file_name);
}

}
