<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Services\Chat\ChatService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    public function __construct(private ChatService $chat) {}

    /** Stream an assistant reply over Server-Sent Events. */
    public function stream(Request $request, Conversation $conversation): StreamedResponse
    {
        abort_unless($conversation->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:8000'],
        ]);

        $generator = $this->chat->stream($conversation, $data['message']);

        $response = new StreamedResponse(function () use ($generator) {
            foreach ($generator as $event) {
                echo 'data: '.json_encode($event)."\n\n";
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                flush();
            }
            echo "data: [DONE]\n\n";
            flush();
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }
}
