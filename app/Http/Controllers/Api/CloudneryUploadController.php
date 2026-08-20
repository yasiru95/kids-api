<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Support\Facades\Storage;


class CloudneryUploadController extends Controller
{
    public function upload_images(Request $request )
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
        $manager = new ImageManager(new Driver());

        $uploadedFiles = [];

        foreach ($images as $index => $image) {

            if (!$image) {
                continue;
            }

            // return response()->json([
            //     'status' => false,
            //     'message' => 'No image file found at index ' . $index,
            //     'index' =>  $image->getClientOriginalName()
            // ], 422);
           
            
            if($image->getClientOriginalName() === 'cover'){
               return response()->json([
                    'status' => false,
                    'message' => 'Cover image should be uploaded separately with the name "cover"'
                ], 422);
            }

            // ✅ Read image
            $img = $manager->read($image);

            // Convert to WebP in memory
            $webpData = (string) $img->toWebp(80);
            // Optional resize
            // $img->cover(1080, 1920);

             // ✅ Image size in MB
            $sizeInMB = round($image->getSize() / 1024 / 1024, 2);

            // ✅ Rename image
            // $fileName = $storyName . '-story-image-' . ($index + 1). '.webp';
            
            try {
            $fileName = pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME);

            // ✅ S3 path
            $filePath = "Stories/{$storyName}/images/{$fileName}";

            // ✅ Upload to S3
            Storage::disk('s3')->put(
            $filePath,
            $webpData,
            [
            'ContentType' => 'image/webp',
            'CacheControl' => 'public, max-age=31536000'
            ]
            );

            // ✅ Get S3 URL

            $url ="https://kidsstoryflix-images.s3.us-east-1.amazonaws.com/Stories/{$storyName}/images/{$fileName}";

            // $url = 'https://' . config('services.s3.s3_bucket')
            // . '.s3.'
            // . config('services.s3.region')
            // . '.amazonaws.com/'
            // . $filePath;

            $uploadedFiles[] = $url;


            // // ✅ Upload to Cloudinary 
            // $uploaded = Cloudinary::uploadApi()->upload(
            // // $image->getRealPath(),
            //   'data:image/webp;base64,' . base64_encode($webpData),
            // [

            // 'folder' => "stories/{$storyName}/images",
            // 'public_id' =>$fileName!='cover'? "page-".($index + 1): 'cover', // Use original name for cover, page-1, page-2... for others
            // 'format' => 'webp',
            // 'overwrite' => true,
            // 'quality' => 'auto',
            // 'fetch_format' => 'webp',
                

            // // ✅ Tags
            // 'tags' => [
            // 'kids-story',
            //  $storyName,
            // 'storybook'
            // ],

            // // ✅ Context metadata
            // 'context' => [
            // 'alt' => $request->story_name,
            // 'caption' => "Story image " . ($index + 1),
            // 'story' => $storyName
            // ],

         
            
            
            // ] 
            
            
            
            // );
            } catch (\Throwable $th) {
                return response()->json([
                    'status' => false,
                    'message' => 'Upload failed: ' . $th->getMessage()
                ], 500);
            }


            

            // ✅ Secure URL 
            // $uploadedFiles[] = $uploaded['secure_url'];
            // Full path
            // $filePath = $folderPath . '/' . $fileName;

            // // ✅ Save as webp
            // $img->toWebp(80)->save($filePath);

            // ✅ Public URL
            // $uploadedFiles[] = url("uploads/stories/{$storyName}/{$fileName}");
        }
        

        return response()->json([
            'status' => true,
            'story_name' => $storyName,
            'count-' => count($uploadedFiles),
            'images' => $uploadedFiles
        ]);
    }



public function uploadAudio(Request $request)
{
    // ✅ Validate
   try {
    $validated = $request->validate([
        'story_name' => 'required|string',
        'audio' => 'required|file|mimes:mp3,wav,aac,ogg'
    ]);
   }
    catch (\Throwable $th) {
        return response()->json([
            'status' => false,
            'message' => 'Validation failed: ' . $th->getMessage()
        ], 422);
    }

    $storyName = Str::slug($request->story_name);

    $audioFile = $request->file('audio');

    $fileName = $storyName . '-audio-' . time();

    try {

        // ✅ Upload to Cloudinary

        $uploaded = Cloudinary::uploadApi()->upload(
            $audioFile->getRealPath(),
            [
                'resource_type' => 'video', // IMPORTANT for mp3/audio in Cloudinary
                'folder' => "stories/{$storyName}/audio",
                'public_id' => $fileName,

                // Optional optimizations
                'overwrite' => true,
                'format' => 'mp3',

                // Metadata
                'context' => [
                    'story' => $storyName,
                    'type' => 'audio'
                ]
            ]
        );

        return response()->json([
            'status' => true,
            'story_name' => $storyName,
            'audio_url' => $uploaded['secure_url']
        ]);

    } catch (\Throwable $th) {
        return response()->json([
            'status' => false,
            'message' => 'Audio upload failed: ' . $th->getMessage()
        ], 500);
    }
}



    
    
    

}
