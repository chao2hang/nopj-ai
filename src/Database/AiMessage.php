<?php

namespace Nopj\Ai\Database;

use Flarum\Database\AbstractModel;
use Flarum\Post\Post;

class AiMessage extends AbstractModel
{
    protected $table = 'nopj_ai_messages';

    protected $casts = [
        'session_id' => 'int',
        'post_id' => 'int',
    ];

    public $timestamps = false;

    protected $dates = ['created_at'];

    public function session()
    {
        return $this->belongsTo(AiSession::class, 'session_id');
    }

    public function post()
    {
        return $this->belongsTo(Post::class, 'post_id');
    }

    public static function createMessage(int $sessionId, string $role, string $content, ?int $postId = null): self
    {
        $message = new static();
        $message->session_id = $sessionId;
        $message->role = $role;
        $message->content = $content;
        $message->post_id = $postId;
        $message->created_at = now();
        $message->save();

        return $message;
    }
}
