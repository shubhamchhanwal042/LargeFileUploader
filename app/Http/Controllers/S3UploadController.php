<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Aws\S3\S3Client;
use Illuminate\Support\Str;
use App\Models\Video;

class S3UploadController extends Controller
{

private function getS3()
{
    return new S3Client([
        'version' => 'latest',
        'region' => config('filesystems.disks.s3.region'),
        'credentials' => [
            'key' => config('filesystems.disks.s3.key'),
            'secret' => config('filesystems.disks.s3.secret'),
        ]
    ]);
}

public function initUpload(Request $request)
{

    $fileName = time().'_'.$request->file_name;

    $s3 = $this->getS3();

    $result = $s3->createMultipartUpload([
        'Bucket' => config('filesystems.disks.s3.bucket'),
        'Key' => "videos/".$fileName
    ]);

    return response()->json([
        "uploadId" => $result['UploadId'],
        "key" => $result['Key']
    ]);
}

public function getPresignedUrl(Request $request)
{

    $s3 = $this->getS3();

    $command = $s3->getCommand('UploadPart', [
        'Bucket' => config('filesystems.disks.s3.bucket'),
        'Key' => $request->key,
        'UploadId' => $request->uploadId,
        'PartNumber' => $request->partNumber
    ]);

    $url = $s3->createPresignedRequest($command, '+1 hour');

    return response()->json([
        "url" => (string) $url->getUri()
    ]);
}

public function completeUpload(Request $request)
{

    $s3 = $this->getS3();

    $result = $s3->completeMultipartUpload([
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
    


    return response()->json([
        "success" => true,
        "location" => $result['Location']
    ]);
}

}
