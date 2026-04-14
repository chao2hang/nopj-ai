<?php

namespace Nopj\Ai\Job;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;
use Nopj\Ai\Service\AiReplyOrchestrator;

class ProcessAiReplyJob implements ShouldQueue
{
    use Queueable, SerializesModels;

    protected $postId;
    protected $aiUserId;
    protected $discussionId;
    protected $typingPostId;

    public function __construct(int $postId, int $aiUserId, int $discussionId, ?int $typingPostId = null)
    {
        $this->postId = $postId;
        $this->aiUserId = $aiUserId;
        $this->discussionId = $discussionId;
        $this->typingPostId = $typingPostId;
    }

    public function handle(AiReplyOrchestrator $orchestrator): void
    {
        $orchestrator->processReplyForTypingPost($this->postId, $this->discussionId, $this->aiUserId, $this->typingPostId);
    }
}
