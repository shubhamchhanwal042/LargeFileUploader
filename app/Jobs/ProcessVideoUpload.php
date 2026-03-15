<?php
namespace App\Jobs;

use App\Models\Video;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

// class ProcessVideoUpload implements ShouldQueue
// {
//     use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

//     public $video; // public is better for queued models

//     // Retry settings
//     public $tries = 3; // Retry 3 times if job fails
//     public $backoff = 10; // Wait 10 seconds before retry

//     public function __construct($video) // Remove strict type Video $video
//     {
//         $this->video = $video;
//     }

//     public function handle()
//     {
//         // Optional: Check if video exists
//         if(!$this->video) return;

//         // Simulate processing
//         sleep(5);

//         $this->video->status = 'processed';
//         $this->video->save();

//         // Send email
//         Mail::raw("Video '{$this->video->file_name}' has been processed.", function($message){
//             $message->to('shubhamchhanwal042@gmail.com')
//                     ->subject('Video Upload Completed');
//         });
//     }
// }

class ProcessVideoUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $video;

    // Retry settings
    public $tries = 3; // Retry 3 times if job fails
    public $backoff = 10; // Wait 10 seconds before retry

    public function __construct($video)
    {
        $this->video = $video;
    }

    public function handle()
    {
        try {
            // Simulate processing
            sleep(5);

            $this->video->status = 'completed';
            $this->video->save();

            // Send email
            Mail::raw("Video '{$this->video->file_name}' has been processed.", function($message){
                $message->to('shubhamc04523@gmail.com')
                        ->subject('Video Upload Completed');
            });

        } catch (Throwable $e) {
            \Log::error("Job failed for video ID {$this->video->id}: ".$e->getMessage());
            throw $e; // This triggers retry
        }
    }
}
?>
