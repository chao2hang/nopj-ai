<?php

namespace Nopj\Ai\Service;

use Flarum\Discussion\Discussion;
use Flarum\Post\CommentPost;
use Flarum\Post\Post;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Nopj\Ai\Database\AiMessage;
use Nopj\Ai\Database\AiSession;
use Psr\Log\LoggerInterface;
use s9e\TextFormatter\Utils;

class AiReplyOrchestrator
{
    protected $settings;
    protected $aiService;
    protected $replySupport;
    protected $cache;
    protected $logger;

    public function __construct(
        SettingsRepositoryInterface $settings,
        AiService $aiService,
        AiReplySupport $replySupport,
        CacheRepository $cache,
        LoggerInterface $logger
    ) {
        $this->settings = $settings;
        $this->aiService = $aiService;
        $this->replySupport = $replySupport;
        $this->cache = $cache;
        $this->logger = $logger;
    }

    public function resolveConfiguredAiUser(): ?User
    {
        $aiUserId = $this->settings->get('nopj-ai.ai_user_id');

        if (empty($aiUserId)) {
            return null;
        }

        if (is_numeric($aiUserId)) {
            return User::find((int) $aiUserId);
        }

        return User::where('username', $aiUserId)->first()
            ?: User::where('display_name', $aiUserId)->first();
    }

    public function shouldReplyToPost(CommentPost $post, User $aiUser): bool
    {
        $userMentionIds = array_map('intval', Utils::getAttributeValues($post->parsed_content, 'USERMENTION', 'id'));
        if (in_array($aiUser->id, $userMentionIds, true)) {
            return true;
        }

        $mentionedPostIds = array_map('intval', Utils::getAttributeValues($post->parsed_content, 'POSTMENTION', 'id'));
        if (empty($mentionedPostIds)) {
            return false;
        }

        return Post::query()
            ->whereIn('id', $mentionedPostIds)
            ->where('user_id', $aiUser->id)
            ->exists();
    }

    public function processReply(int $postId, int $discussionId, int $aiUserId): void
    {
        $this->processReplyForTypingPost($postId, $discussionId, $aiUserId, null);
    }

    public function createTypingPostForReply(int $discussionId, int $aiUserId): ?CommentPost
    {
        $discussion = Discussion::find($discussionId);
        if (!$discussion) {
            $this->logger->error("[nopj-ai] Discussion #{$discussionId} not found when creating typing post");
            return null;
        }

        $aiUser = User::find($aiUserId);
        if (!$aiUser) {
            $this->logger->error("[nopj-ai] AI user #{$aiUserId} not found when creating typing post");
            return null;
        }

        return $this->replySupport->createTypingPost($discussion, $aiUser);
    }

    public function processReplyForTypingPost(int $postId, int $discussionId, int $aiUserId, ?int $typingPostId): void
    {
        $lockKey = $this->getProcessingKey($postId, $aiUserId);
        $doneKey = $this->getDoneKey($postId, $aiUserId);

        if ($this->cache->get($doneKey)) {
            $this->logger->info("[nopj-ai] Skip duplicate completed reply for post #{$postId}");
            return;
        }

        if (!$this->cache->add($lockKey, true, 600)) {
            $this->logger->info("[nopj-ai] Skip concurrent reply processing for post #{$postId}");
            return;
        }

        $typingPost = null;
        $aiUser = null;

        try {
            $post = Post::find($postId);
            if (!$post) {
                $this->logger->error("[nopj-ai] Trigger post #{$postId} not found");
                return;
            }

            $discussion = Discussion::find($discussionId);
            if (!$discussion) {
                $this->logger->error("[nopj-ai] Discussion #{$discussionId} not found");
                return;
            }

            $aiUser = User::find($aiUserId);
            if (!$aiUser) {
                $this->logger->error("[nopj-ai] AI user #{$aiUserId} not found");
                return;
            }

            $session = AiSession::findByDiscussionAndAiUser($discussion->id, $aiUserId)
                ?: AiSession::createSession($discussion->id, $aiUserId);

            $contextPostsCount = (int) $this->settings->get('nopj-ai.context_posts_count', '5');
            $contextText = $this->replySupport->buildContextText($discussion->id, $postId, $contextPostsCount);

            $historyMessages = AiMessage::where('session_id', $session->id)
                ->orderBy('created_at', 'asc')
                ->limit(20)
                ->get();

            $messages = [];

            if ($contextText !== '') {
                $messages[] = [
                    'role' => 'user',
                    'content' => "Discussion context:\n\nDiscussion Title: {$discussion->title}\n\nRecent posts:\n{$contextText}",
                ];
            }

            foreach ($historyMessages as $historyMessage) {
                $messages[] = [
                    'role' => $historyMessage->role,
                    'content' => $historyMessage->content,
                ];
            }

            $userContent = $this->replySupport->extractUserContent($post);
            $messages[] = [
                'role' => 'user',
                'content' => $userContent,
            ];

            $this->ensureUserMessage($session->id, $userContent, $postId);

            if ($typingPostId) {
                $typingPost = CommentPost::find($typingPostId);
            }

            if (!$typingPost) {
                $typingPost = $this->replySupport->createTypingPost($discussion, $aiUser);
                $this->logger->info("[nopj-ai] Posted typing indicator (post #{$typingPost->id})");
            }

            $replyContent = $this->aiService->chat($messages);

            if (!$replyContent) {
                $this->replySupport->persistRevision(
                    $typingPost,
                    '❌ 抱歉，大脑短路了无法回复～',
                    $aiUser
                );
                return;
            }

            $replyContent = $this->replySupport->cleanAiResponse($replyContent);
            if ($replyContent === '') {
                $replyContent = 'AI 已收到您的问题，但暂时无法提供回复，请稍后再试。';
            }

            $mentionText = $this->replySupport->buildReplyMentionText($post);
            if ($mentionText !== null) {
                $replyContent = $mentionText . $replyContent;
            }

            $this->replySupport->persistRevision($typingPost, $replyContent, $aiUser);

            AiMessage::createMessage($session->id, 'assistant', $replyContent, $typingPost->id);
            $session->touch();
            $this->cache->forever($doneKey, true);
        } catch (\Throwable $e) {
            $this->logger->error("[nopj-ai] Reply orchestration failed: {$e->getMessage()}");
            $this->logger->error("[nopj-ai] Trace: {$e->getTraceAsString()}");

            if ($typingPost && $aiUser) {
                try {
                    $this->replySupport->persistRevision(
                        $typingPost,
                        '❌ 抱歉，大脑短路了无法回复～',
                        $aiUser
                    );
                } catch (\Throwable $inner) {
                    $this->logger->error("[nopj-ai] Failed to persist orchestration error post: {$inner->getMessage()}");
                }
            }
        } finally {
            $this->cache->forget($lockKey);
        }
    }

    protected function ensureUserMessage(int $sessionId, string $content, int $postId): void
    {
        $exists = AiMessage::query()
            ->where('session_id', $sessionId)
            ->where('role', 'user')
            ->where('post_id', $postId)
            ->exists();

        if (!$exists) {
            AiMessage::createMessage($sessionId, 'user', $content, $postId);
        }
    }

    protected function getProcessingKey(int $postId, int $aiUserId): string
    {
        return "nopj-ai:processing:{$postId}:{$aiUserId}";
    }

    protected function getDoneKey(int $postId, int $aiUserId): string
    {
        return "nopj-ai:done:{$postId}:{$aiUserId}";
    }
}
