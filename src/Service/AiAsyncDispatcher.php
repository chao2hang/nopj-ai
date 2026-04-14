<?php

namespace Nopj\Ai\Service;

use Illuminate\Contracts\Queue\Queue;
use Illuminate\Queue\SyncQueue;
use Nopj\Ai\Job\ProcessAiReplyJob;
use Psr\Log\LoggerInterface;

class AiAsyncDispatcher
{
    protected $queue;
    protected $orchestrator;
    protected $logger;

    public function __construct(Queue $queue, AiReplyOrchestrator $orchestrator, LoggerInterface $logger)
    {
        $this->queue = $queue;
        $this->orchestrator = $orchestrator;
        $this->logger = $logger;
    }

    public function dispatch(int $postId, int $discussionId, int $aiUserId, ?int $typingPostId = null): void
    {
        if ($this->queue instanceof SyncQueue) {
            $this->dispatchAfterResponse($postId, $discussionId, $aiUserId, $typingPostId);
            return;
        }

        $this->queue->push(new ProcessAiReplyJob($postId, $aiUserId, $discussionId, $typingPostId));
        $this->logger->info("[nopj-ai] Dispatched AI reply job to queue for trigger post #{$postId}");
    }

    protected function dispatchAfterResponse(int $postId, int $discussionId, int $aiUserId, ?int $typingPostId): void
    {
        $orchestrator = $this->orchestrator;
        $logger = $this->logger;

        register_shutdown_function(function () use ($orchestrator, $logger, $postId, $discussionId, $aiUserId, $typingPostId) {
            if (function_exists('fastcgi_finish_request')) {
                @fastcgi_finish_request();
            }

            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }

            ignore_user_abort(true);
            set_time_limit(120);

            try {
                $logger->info("[nopj-ai] Running AI reply after response for trigger post #{$postId}");
                $orchestrator->processReplyForTypingPost($postId, $discussionId, $aiUserId, $typingPostId);
            } catch (\Throwable $e) {
                $logger->error("[nopj-ai] Deferred AI reply failed: {$e->getMessage()}");
                $logger->error("[nopj-ai] Trace: {$e->getTraceAsString()}");
            }
        });

        $this->logger->info("[nopj-ai] Scheduled AI reply after response for trigger post #{$postId}");
    }
}
