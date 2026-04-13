<?php

namespace Nopj\Ai\Job;

use Flarum\Discussion\Discussion;
use Flarum\Foundation\DispatchedJob;
use Flarum\Post\CommentPost;
use Flarum\Post\Post;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;
use Nopj\Ai\Database\AiMessage;
use Nopj\Ai\Database\AiSession;
use Nopj\Ai\Service\AiService;

class ProcessAiReplyJob implements ShouldQueue, DispatchedJob
{
    use Queueable, SerializesModels;

    protected $postId;
    protected $aiUserId;
    protected $discussionId;

    public function __construct(int $postId, int $aiUserId, int $discussionId)
    {
        $this->postId = $postId;
        $this->aiUserId = $aiUserId;
        $this->discussionId = $discussionId;
    }

    public function handle(AiService $aiService, SettingsRepositoryInterface $settings)
    {
        $post = Post::find($this->postId);
        if (!$post) {
            return;
        }

        $discussion = Discussion::find($this->discussionId);
        if (!$discussion) {
            return;
        }

        $aiUser = User::find($this->aiUserId);
        if (!$aiUser) {
            return;
        }

        $session = AiSession::findByDiscussionAndAiUser($this->discussionId, $this->aiUserId);
        if (!$session) {
            $session = AiSession::createSession($this->discussionId, $this->aiUserId);
        }

        $contextPostsCount = (int) $settings->get('nopj-ai.context_posts_count', '5');

        $contextPosts = Post::where('discussion_id', $this->discussionId)
            ->where('id', '!=', $this->postId)
            ->whereNotNull('user_id')
            ->orderBy('created_at', 'desc')
            ->limit($contextPostsCount)
            ->get()
            ->reverse();

        $contextText = '';
        foreach ($contextPosts as $contextPost) {
            $username = $contextPost->user ? $contextPost->user->display_name : 'Unknown';
            $contextText .= "[{$username}]: " . strip_tags($contextPost->content) . "\n\n";
        }

        $historyMessages = AiMessage::where('session_id', $session->id)
            ->orderBy('created_at', 'asc')
            ->limit(20)
            ->get();

        $messages = [];

        if (!empty($contextText)) {
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

        $userContent = strip_tags($post->content);
        $userContent = preg_replace('/<mention[^>]*username="([^"]*)"[^>]*><\/mention>/', '@$1', $userContent);
        $messages[] = [
            'role' => 'user',
            'content' => $userContent,
        ];

        AiMessage::createMessage($session->id, 'user', $userContent, $this->postId);

        $replyContent = $aiService->chat($messages);

        if ($replyContent) {
            $aiPost = new CommentPost();
            $aiPost->discussion_id = $this->discussionId;
            $aiPost->user_id = $this->aiUserId;
            $aiPost->content = $replyContent;
            $aiPost->created_at = now();
            $aiPost->is_private = false;
            $aiPost->save();

            $discussion->comment_count++;
            $discussion->last_post_id = $aiPost->id;
            $discussion->last_posted_at = $aiPost->created_at;
            $discussion->last_post_user_id = $this->aiUserId;
            $discussion->save();

            AiMessage::createMessage($session->id, 'assistant', $replyContent, $aiPost->id);

            $session->touch();
        }
    }
}
