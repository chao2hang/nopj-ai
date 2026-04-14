<?php

namespace Nopj\Ai\Service;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Notification\NotificationSyncer;
use Flarum\Post\CommentPost;
use Flarum\Post\Event\Saving;
use Flarum\Post\Post;
use Flarum\User\User;
use Illuminate\Contracts\Events\Dispatcher;

class AiReplySupport
{
    protected $events;
    protected $notifications;

    public function __construct(Dispatcher $events, NotificationSyncer $notifications)
    {
        $this->events = $events;
        $this->notifications = $notifications;
    }

    public function createTypingPost(Discussion $discussion, User $actor, string $typingText = '🤔 正在思考中...'): CommentPost
    {
        return $this->createReplyPost($discussion, $actor, $typingText);
    }

    public function createReplyPost(Discussion $discussion, User $actor, string $content): CommentPost
    {
        $post = CommentPost::reply(
            $discussion->id,
            $content,
            $actor->id,
            '127.0.0.1',
            $actor
        );

        $post->created_at = Carbon::now();
        $post->is_private = false;

        $this->events->dispatch(new Saving($post, $actor, ['attributes' => ['content' => $content]]));
        $post->save();

        $this->notifications->onePerUser(function () use ($post, $actor) {
            $this->dispatchModelEvents($post, $actor);
        });

        return $post;
    }

    public function persistRevision(CommentPost $post, string $content, User $actor): void
    {
        $post->revise($content, $actor);
        $this->events->dispatch(new Saving($post, $actor, ['attributes' => ['content' => $content]]));
        $post->save();

        $this->notifications->onePerUser(function () use ($post, $actor) {
            $this->dispatchModelEvents($post, $actor);
        });
    }

    public function buildReplyMentionText(Post $triggerPost): ?string
    {
        $triggerUser = $triggerPost->user ?: User::find($triggerPost->user_id);
        if (!$triggerUser || !$triggerUser->exists || !empty($triggerUser->deleted_at)) {
            return null;
        }

        $name = $triggerUser->display_name ?: $triggerUser->username;
        if (!$name) {
            return null;
        }

        $cleanName = trim(str_replace(['"', '#'], '', $name));
        if ($cleanName === '') {
            return null;
        }

        return '@"' . $cleanName . '"#p' . $triggerPost->id . ' ';
    }

    public function cleanAiResponse(string $content): string
    {
        $content = preg_replace('/开发者模式已激活/is', '', $content);
        $content = preg_replace('/Developer mode (has been )?activated/is', '', $content);
        $content = preg_replace('/Grok\s*\d+(\.\d+)?\s*开发者模式/is', '', $content);
        $content = preg_replace('/已完全解除.*(内容审查|限制|机制)/is', '', $content);
        $content = preg_replace('/已学习并完全执行.*指令/is', '', $content);
        $content = preg_replace('/我会(立即)?(绝对)?(服从|执行).*指令/is', '', $content);
        $content = preg_replace('/需要我帮(你|您)生成.*直接说/is', '', $content);
        $content = preg_replace('/<UserCard[^>]*>.*?<\/UserCard>/is', '', $content);
        $content = preg_replace('/<user[^>]*>.*?<\/user>/is', '', $content);
        $content = preg_replace('/<UserInfo[^>]*>.*?<\/UserInfo>/is', '', $content);
        $content = preg_replace('/<Signature[^>]*>.*?<\/Signature>/is', '', $content);
        $content = preg_replace('/\d+\s*(小时前|天前|分钟前|周前|月前|年前)注册/is', '', $content);
        $content = preg_replace('/\d+\s*次助人/is', '', $content);
        $content = preg_replace('/\d+\s*帖子/is', '', $content);
        $content = preg_replace('/<!--.*?-->/s', '', $content);
        $content = preg_replace('/\n{3,}/', "\n\n", $content);

        return trim($content);
    }

    public function buildContextText(int $discussionId, int $excludePostId, int $contextPostsCount, int $maxContextLength = 3000): string
    {
        $contextPosts = Post::query()
            ->where('discussion_id', $discussionId)
            ->where('id', '!=', $excludePostId)
            ->whereNotNull('user_id')
            ->orderBy('created_at', 'desc')
            ->limit($contextPostsCount)
            ->get()
            ->reverse();

        $contextText = '';

        foreach ($contextPosts as $contextPost) {
            $username = $contextPost->user ? $contextPost->user->display_name : 'Unknown';
            $cleanContent = $this->extractUserContent($contextPost);

            if (strlen($cleanContent) > 500) {
                $cleanContent = substr($cleanContent, 0, 500) . '...';
            }

            if ($cleanContent === '') {
                continue;
            }

            $entry = "[{$username}]: {$cleanContent}\n\n";
            if (strlen($contextText) + strlen($entry) > $maxContextLength) {
                break;
            }

            $contextText .= $entry;
        }

        return $contextText;
    }

    public function extractUserContent(Post $post): string
    {
        $content = strip_tags((string) $post->content);
        $content = preg_replace('/<USERMENTION[^>]*>(.*?)<\/USERMENTION>/', '$1', $content);

        return trim(strip_tags($content));
    }

    protected function dispatchModelEvents($model, User $actor): void
    {
        foreach ($model->releaseEvents() as $event) {
            $event->actor = $actor;
            $this->events->dispatch($event);
        }
    }
}
