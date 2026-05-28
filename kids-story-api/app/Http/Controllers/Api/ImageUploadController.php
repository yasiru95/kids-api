<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class ImageUploadController extends Controller
{
    public function upload(Request $request)
    {
        // ✅ Validate request
        $request->validate([
            'story_name' => 'required|string',
            'images' => 'required',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp'
        ]);

        // ✅ Convert story name to slug
        $storyName = Str::slug($request->story_name);

        // ✅ Get uploaded files
        $images = $request->file('images', []);

        // Convert single image to array
        if (!is_array($images)) {
            $images = [$images];
        }

       
        // // ✅ Create folder
        // $folderPath = public_path("uploads/stories/{$storyName}");

        // if (!file_exists($folderPath)) {
        //     mkdir($folderPath, 0777, true);
        // }

        // // ✅ Image Manager
        // $manager = new ImageManager(new Driver());

        $uploadedFiles = [];

        foreach ($images as $index => $image) {

            if (!$image) {
                continue;
            }

            // ✅ Read image
            // $img = $manager->read($image);

            // Optional resize
            // $img->cover(1080, 1920);

            // ✅ Rename image
            $fileName = $storyName . '-story-image-' . ($index + 1);
            
            try {

            // ✅ Upload to Cloudinary 
            $uploaded = Cloudinary::uploadApi()->upload(
            $image->getRealPath(),
            [

            'folder' => "stories/{$storyName}",
            'public_id' => $fileName, 
            'format' => 'webp',
            'overwrite' => true,
            'quality' => 'auto',
            'fetch_format' => 'auto',

            // ✅ Tags
            'tags' => [
            'kids-story',
             $storyName,
            'storybook'
            ],

            // ✅ Context metadata
            'context' => [
            'alt' => $request->story_name,
            'caption' => "Story image " . ($index + 1),
            'story' => $storyName
            ],

         
            
            
            ] 
            
            
            
            );
            } catch (\Throwable $th) {
                return response()->json([
                    'status' => false,
                    'message' => 'Upload failed: ' . $th->getMessage()
                ], 500);
            }


            

            // ✅ Secure URL 
            $uploadedFiles[] = $uploaded['secure_url'];
            // Full path
            // $filePath = $folderPath . '/' . $fileName;

            // ✅ Save as webp
            // $img->toWebp(80)->save($filePath);

            // ✅ Public URL
            // $uploadedFiles[] = url("uploads/stories/{$storyName}/{$fileName}");
        }
        

        return response()->json([
            'status' => true,
            'story_name' => $storyName,
            'count' => count($uploadedFiles),
            'images' => $uploadedFiles
        ]);
    }
}
