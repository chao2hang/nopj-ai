<?php

namespace Nopj\Ai\Database;

use Flarum\Database\AbstractModel;
use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\User\User;

class AiSession extends AbstractModel
{
    protected $table = 'nopj_ai_sessions';

    protected $casts = [
        'discussion_id' => 'int',
        'ai_user_id' => 'int',
    ];

    public $timestamps = true;

    public function discussion()
    {
        return $this->belongsTo(Discussion::class);
    }

    public function aiUser()
    {
        return $this->belongsTo(User::class, 'ai_user_id');
    }

    public function messages()
    {
        return $this->hasMany(AiMessage::class, 'session_id');
    }

    public static function findByDiscussionAndAiUser(int $discussionId, int $aiUserId): ?self
    {
        return static::where('discussion_id', $discussionId)
            ->where('ai_user_id', $aiUserId)
            ->latest('updated_at')
            ->first();
    }

    public static function createSession(int $discussionId, int $aiUserId): self
    {
        $session = new static();
        $session->discussion_id = $discussionId;
        $session->ai_user_id = $aiUserId;
        $session->session_uuid = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $session->save();

        return $session;
    }
}
