<?php

namespace Nopj\Ai\Listener;

use Flarum\Post\CommentPost;
use Flarum\Post\Event\Posted;
use Nopj\Ai\Service\AiAsyncDispatcher;
use Nopj\Ai\Service\AiReplyOrchestrator;
use Psr\Log\LoggerInterface;

class TriggerAiReplyListener
{
    protected $asyncDispatcher;
    protected $orchestrator;
    protected $logger;

    public function __construct(AiAsyncDispatcher $asyncDispatcher, AiReplyOrchestrator $orchestrator, LoggerInterface $logger)
    {
        $this->asyncDispatcher = $asyncDispatcher;
        $this->orchestrator = $orchestrator;
        $this->logger = $logger;
    }

    public function handle(Posted $event): void
    {
        if (!$event->post instanceof CommentPost) {
            return;
        }

        $aiUser = $this->orchestrator->resolveConfiguredAiUser();
        if (!$aiUser) {
            return;
        }

        if (!$this->orchestrator->shouldReplyToPost($event->post, $aiUser)) {
            return;
        }

        $typingPost = $this->orchestrator->createTypingPostForReply($event->post->discussion_id, $aiUser->id);
        if (!$typingPost) {
            $this->logger->error("[nopj-ai] Failed to create typing post for trigger post #{$event->post->id}");
            return;
        }

        $this->asyncDispatcher->dispatch(
            $event->post->id,
            $event->post->discussion_id,
            $aiUser->id,
            $typingPost->id
        );

        $this->logger->info("[nopj-ai] Accepted AI reply trigger for post #{$event->post->id} with typing post #{$typingPost->id}");
    }
}
