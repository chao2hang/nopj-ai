<?php

namespace Nopj\Ai\Api\Controller;

use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Nopj\Ai\Service\AiAsyncDispatcher;
use Nopj\Ai\Service\AiReplyOrchestrator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ProcessAiAsyncController implements RequestHandlerInterface
{
    protected $asyncDispatcher;
    protected $orchestrator;

    public function __construct(AiAsyncDispatcher $asyncDispatcher, AiReplyOrchestrator $orchestrator)
    {
        $this->asyncDispatcher = $asyncDispatcher;
        $this->orchestrator = $orchestrator;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Immediately return 202 Accepted
        // The actual processing happens in the background
        $body = $request->getParsedBody();
        $postId = Arr::get($body, 'postId');
        $discussionId = Arr::get($body, 'discussionId');
        $aiUserId = Arr::get($body, 'aiUserId');

        if (!$postId || !$discussionId || !$aiUserId) {
            return new JsonResponse(['status' => 'error', 'message' => 'Missing parameters'], 400);
        }

        $typingPost = $this->orchestrator->createTypingPostForReply((int) $discussionId, (int) $aiUserId);
        if (!$typingPost) {
            return new JsonResponse(['status' => 'error', 'message' => 'Failed to create typing post'], 500);
        }

        $this->asyncDispatcher->dispatch((int) $postId, (int) $discussionId, (int) $aiUserId, $typingPost->id);

        return new JsonResponse(['status' => 'accepted'], 202);
    }
}
