<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Video;
use App\Jobs\ProcessVideoUpload;
use Illuminate\Http\File;
use Aws\S3\MultipartUploader;
use Aws\Exception\MultipartUploadException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
class VideoUploadController extends Controller
{
    public function index()
    {
        return view('upload');
    }

    // public function upload(Request $request)
    // {
    //     try {
    //         // Basic validation (don't check mime type yet, we will validate later)
    //         $request->validate([
    //             'file' => 'required|file',
    //             'name' => 'required|string',
    //             'chunk' => 'required|integer',
    //             'totalChunks' => 'required|integer'
    //         ]);

    //         $file = $request->file('file');
    //         $name = $request->input('name');
    //         $chunkIndex = $request->input('chunk');
    //         $totalChunks = $request->input('totalChunks');

    //         // Temporary folder for chunks
    //         $tmpDir = storage_path("app/videos/tmp/$name");
    //         if(!file_exists($tmpDir)){
    //             mkdir($tmpDir, 0755, true);
    //         }

    //         // Save current chunk
    //         $chunkPath = "$tmpDir/chunk_$chunkIndex";
    //         $file->move($tmpDir, "chunk_$chunkIndex");

    //         // Check if all chunks uploaded
    //         $uploadedChunks = glob("$tmpDir/chunk_*");
    //         if(count($uploadedChunks) == $totalChunks){
    //             // Merge chunks into a final file
    //             $finalFileName = time() . '_' . $name;
    //             $localFinalPath = storage_path("app/videos/$finalFileName");
    //             $out = fopen($localFinalPath, 'wb');
    //             sort($uploadedChunks); // Ensure correct order

    //             foreach($uploadedChunks as $chunkFile){
    //                 fwrite($out, file_get_contents($chunkFile));
    //             }
    //             fclose($out);

    //             // Validate MIME type
    //             $finfo = finfo_open(FILEINFO_MIME_TYPE);
    //             $mimeType = finfo_file($finfo, $localFinalPath);
    //             finfo_close($finfo);

    //             if(!in_array($mimeType, ['video/mp4', 'video/quicktime', 'video/x-msvideo'])){
    //                 unlink($localFinalPath);
    //                 return response()->json(['error' => 'Invalid file type'], 400);
    //             }

    //             // Upload to S3
    //             $s3Path = Storage::disk('s3')->putFileAs(
    //                 'videos',               // Folder in S3
    //                 new File($localFinalPath),
    //                 $finalFileName,
    //                 'public'               // or 'private'
    //             );

    //             // Save DB record
    //             $video = Video::create([
    //                 'file_name' => $finalFileName,
    //                 'file_path' => $s3Path,
    //                 'status' => 'processing'
    //             ]);

    //             // Dispatch job for processing/email
    //             ProcessVideoUpload::dispatch($video);

    //             // Clean temp files
    //             foreach($uploadedChunks as $chunkFile) unlink($chunkFile);
    //             rmdir($tmpDir);
    //             unlink($localFinalPath);
    //         }

    //         // Calculate upload progress
    //         $progress = intval((count(glob("$tmpDir/chunk_*")) / $totalChunks) * 100);
    //         if(count($uploadedChunks) == $totalChunks) $progress = 100;

    //         return response()->json([
    //             'success' => true,
    //             'progress' => $progress
    //         ]);

    //     } catch (\Throwable $e) {
    //         return response()->json([
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    public function upload(Request $request)
    {

        
        set_time_limit(0);
        ini_set('max_execution_time', 0);
    
        try {
    
            Log::info("Upload request received", $request->all());
    
            $request->validate([
                'file' => 'required|file',
                'name' => 'required|string',
                'chunk' => 'required|integer',
                'totalChunks' => 'required|integer',
                'upload_id' => 'required|string'
            ]);
    
            $file = $request->file('file');
            $name = $request->input('name');
            $chunkIndex = (int)$request->input('chunk');
            $totalChunks = (int)$request->input('totalChunks');
            $uploadId = $request->input('upload_id');
    
            Log::info("Chunk received", [
                'file_name' => $name,
                'chunk_index' => $chunkIndex,
                'total_chunks' => $totalChunks
            ]);
    
            $tmpDir = storage_path("app/videos/tmp/$uploadId");
    
            if (!file_exists($tmpDir)) {
                mkdir($tmpDir, 0755, true);
                Log::info("Temp directory created: " . $tmpDir);
            }
    
            $chunkPath = "$tmpDir/chunk_$chunkIndex";
    
            if (!file_exists($chunkPath)) {
                $file->move($tmpDir, "chunk_$chunkIndex");
                Log::info("Chunk stored", ['path' => $chunkPath]);
            }
    
            $uploadedChunks = glob("$tmpDir/chunk_*");
    
            Log::info("Uploaded chunks count", [
                'count' => count($uploadedChunks),
                'expected' => $totalChunks
            ]);
    
            /*
            |--------------------------------------------------------------------------
            | Merge Chunks When All Uploaded
            |--------------------------------------------------------------------------
            */
    
            if (count($uploadedChunks) == $totalChunks) {
    
                Log::info("All chunks uploaded. Starting merge.");
    
                $finalFileName = time() . '_' . $name;
                $localFinalPath = storage_path("app/videos/$finalFileName");
    
                $out = fopen($localFinalPath, 'wb');

                for($i = 0; $i < $totalChunks; $i++){
                
                    $chunkFile = "$tmpDir/chunk_$i";
                
                    if(!file_exists($chunkFile)){
                        Log::error("Missing chunk",["chunk"=>$i]);
                
                        return response()->json([
                            "error" => "Missing chunk $i"
                        ],500);
                    }
                
                    $chunk = fopen($chunkFile,'rb');
                
                    stream_copy_to_stream($chunk,$out);
                
                    fclose($chunk);
                }
                
                fclose($out);
    
                Log::info("Chunks merged successfully", [
                    'final_path' => $localFinalPath
                ]);
    
                /*
                |--------------------------------------------------------------------------
                | Validate Video Type
                |--------------------------------------------------------------------------
                */
    
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $localFinalPath);
                finfo_close($finfo);
    
                Log::info("Detected MIME type", ['mime' => $mimeType]);
    
                if (!in_array($mimeType, ['video/mp4','video/quicktime','video/x-msvideo'])) {
    
                    unlink($localFinalPath);
    
                    return response()->json([
                        'error' => 'Invalid file type'
                    ], 400);
                }
    
                /*
                |--------------------------------------------------------------------------
                | Upload to S3
                |--------------------------------------------------------------------------
                */
    
                Log::info("Uploading to S3...");
    
                $s3Path = 'videos/' . $finalFileName;
    
                


                $s3 = Storage::disk('s3')->getClient();

                $uploader = new MultipartUploader($s3, $localFinalPath, [
                    'bucket' => config('filesystems.disks.s3.bucket'),
                    'key'    => 'videos/'.$finalFileName,
                    'part_size' => 10 * 1024 * 1024 // 10MB parts
                ]);

                try {

                    $result = $uploader->upload();

                    Log::info("S3 multipart upload complete", [
                        'location' => $result['ObjectURL']
                    ]);

                    $success = true;

                } catch (MultipartUploadException $e) {

                    Log::error("Multipart upload failed", [
                        'error' => $e->getMessage()
                    ]);

                    $success = false;
                }
                /*
                |--------------------------------------------------------------------------
                | Save DB Record
                |--------------------------------------------------------------------------
                */
    
                $video = Video::create([
                    'file_name' => $finalFileName,
                    'file_path' => $s3Path,
                    'status' => 'processing'
                ]);
    
                Log::info("Database record created", [
                    'video_id' => $video->id
                ]);
    
                ProcessVideoUpload::dispatch($video);
    
                Log::info("Queue job dispatched");
    
                /*
                |--------------------------------------------------------------------------
                | Cleanup Temporary Files
                |--------------------------------------------------------------------------
                */
    
                foreach ($uploadedChunks as $chunkFile) {
                    unlink($chunkFile);
                }
    
                rmdir($tmpDir);
                unlink($localFinalPath);
    
                Log::info("Temporary files deleted");
    
                /*
                |--------------------------------------------------------------------------
                | Return Completed
                |--------------------------------------------------------------------------
                */
    
                return response()->json([
                    'success' => true,
                    'completed' => true,
                    'message' => 'Upload completed successfully'
                ]);
            }
    
            /*
            |--------------------------------------------------------------------------
            | Return Progress
            |--------------------------------------------------------------------------
            */
    
            $progress = intval((count($uploadedChunks) / $totalChunks) * 100);    
            return response()->json([
                'success' => true,
                'progress' => $progress,
                'completed' => false
            ]);
    
        } catch (\Throwable $e) {
    
            Log::error("Upload error", [
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ]);
    
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // public function status(Request $request)
    // {

    // $uploadId = $request->upload_id;

    // $tmpDir = storage_path("app/videos/tmp/$uploadId");

    // if(!file_exists($tmpDir)){
    // return response()->json([
    // 'uploadedChunks'=>[]
    // ]);
    // }

    // $chunks = glob("$tmpDir/chunk_*");

    // $uploaded = [];

    // foreach($chunks as $chunk){

    // $uploaded[] = intval(str_replace('chunk_','',basename($chunk)));

    // }

    // return response()->json([
    // 'uploadedChunks'=>$uploaded
    // ]);

    // }

    // public function status(Request $request)
    // {

    // $uploadId = $request->upload_id;

    // $tmpDir = storage_path("app/videos/tmp/$uploadId");

    // if(!file_exists($tmpDir)){
    // return response()->json([
    // "uploadedChunks"=>[],
    // "completed"=>false
    // ]);
    // }

    // $chunks = glob("$tmpDir/chunk_*");

    // $uploadedChunks = [];

    // foreach($chunks as $chunk){

    // $uploadedChunks[] = intval(str_replace("chunk_","",basename($chunk)));

    // }

    // sort($uploadedChunks);

    // return response()->json([
    // "uploadedChunks"=>$uploadedChunks,
    // "completed"=>false
    // ]);

    // }

    public function status(Request $request)
{

    $uploadId = $request->upload_id;

    $tmpDir = storage_path("app/videos/tmp/$uploadId");

    if(!file_exists($tmpDir)){
        return response()->json([
            "uploadedChunks"=>[],
            "completed"=>false
        ]);
    }

    $chunks = glob($tmpDir.'/chunk_*');
    $uploadedChunks = [];

    foreach($chunks as $chunk){

        $uploadedChunks[] = intval(str_replace("chunk_","",basename($chunk)));

    }

    sort($uploadedChunks);

    return response()->json([
        "uploadedChunks"=>$uploadedChunks,
        "completed"=>false
    ]);

}


public function init(Request $request)
{
    $request->validate([
        'file_name' => 'required|string'
    ]);

    $client = Storage::disk('s3')->getClient();

    $key = 'videos/' . Str::uuid() . '_' . $request->file_name;

    $result = $client->createMultipartUpload([
        'Bucket' => config('filesystems.disks.s3.bucket'),
        'Key'    => $key,
    ]);

    return response()->json([
        'uploadId' => $result['UploadId'],
        'key' => $key
    ]);
}

public function complete(Request $request)
{
    $request->validate([
        'uploadId' => 'required',
        'key' => 'required',
        'parts' => 'required|array'
    ]);

    $client = Storage::disk('s3')->getClient();

    $result = $client->completeMultipartUpload([
        'Bucket' => config('filesystems.disks.s3.bucket'),
        'Key' => $request->key,
        'UploadId' => $request->uploadId,
        'MultipartUpload' => [
            'Parts' => $request->parts
        ]
    ]);

    // Save to database
    $video = Video::create([
        'file_name' => basename($request->key),
        'file_path' => $request->key,
        'status' => 'processing'
    ]);

    // Dispatch background job
    ProcessVideoUpload::dispatch($video);

    return response()->json(['success' => true]);
}

public function presigned(Request $request)
{
    $request->validate([
        'uploadId' => 'required',
        'key' => 'required',
        'partNumber' => 'required|integer'
    ]);

    $client = Storage::disk('s3')->getClient();

    $command = $client->getCommand('UploadPart', [
        'Bucket'     => config('filesystems.disks.s3.bucket'),
        'Key'        => $request->key,
        'UploadId'   => $request->uploadId,
        'PartNumber' => (int) $request->partNumber,
    ]);

    $presignedRequest = $client->createPresignedRequest(
        $command,
        '+20 minutes'
    );

    return response()->json([
        'url' => (string) $presignedRequest->getUri()
    ]);
}

}