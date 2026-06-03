<?php

namespace App\Services;

use Aws\Polly\PollyClient;

class PollyService
{
    // public function synthesizeWithMarks($text)
    // {
    //     $polly = new PollyClient([
    //         'version' => 'latest',
    //         'region' => env('AWS_DEFAULT_REGION'),
    //         'credentials' => [
    //             'key' => env('AWS_ACCESS_KEY_ID'),
    //             'secret' => env('AWS_SECRET_ACCESS_KEY'),
    //         ]
    //     ]);

    //     $result = $polly->synthesizeSpeech([
    //         'Text' => $text,
    //         'OutputFormat' => 'json',
    //         'VoiceId' => 'Ruth',
    //         'Engine' => 'long-form', 
    //         'TextType' => 'text',
    //         'SpeechMarkTypes' => ['word']

    //         // 'SpeechMarkTypes' => ['word', 'sentence']
    //     ]);

    //     $stream = $result['AudioStream'];

    //     $lines = explode("\n", $stream->getContents());

    //     $marks = [];

    //     foreach ($lines as $line) {
    //         if (!empty($line)) {
    //             $marks[] = json_decode($line, true);
    //         }
    //     }

    //     return $marks;
    // }
}