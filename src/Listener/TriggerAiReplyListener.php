<?php

namespace Nopj\Ai\Listener;

use Flarum\Post\Event\Posted;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Contracts\Events\Dispatcher;
use Nopj\Ai\Job\ProcessAiReplyJob;

class TriggerAiReplyListener
{
    protected $settings;
    protected $events;

    public function __construct(SettingsRepositoryInterface $settings, Dispatcher $events)
    {
        $this->settings = $settings;
        $this->events = $events;
    }

    public function handle(Posted $event)
    {
        $aiUserId = $this->settings->get('nopj-ai.ai_user_id');

        if (empty($aiUserId)) {
            return;
        }

        $aiUser = User::find($aiUserId);
        if (!$aiUser) {
            return;
        }

        $content = $event->post->content;

        $mentionPattern = '/<mention[^>]*username="([^"]*)"[^>]*><\/mention>/';
        preg_match_all($mentionPattern, $content, $matches);

        if (empty($matches[1])) {
            return;
        }

        $mentionedUsernames = $matches[1];

        if (in_array($aiUser->username, $mentionedUsernames)) {
            $this->events->dispatch(
                new ProcessAiReplyJob(
                    $event->post->id,
                    (int) $aiUserId,
                    $event->post->discussion_id
                )
            );
        }
    }
}
