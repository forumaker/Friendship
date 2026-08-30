<?php

declare(strict_types=1);

namespace forumaker\Friendship\Tests\integration\console;

use Carbon\Carbon;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use forumaker\Friendship\Console\PruneEventsCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

class PruneEventsCommandTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    public function setUp(): void
    {
        parent::setUp();

        $this->extension('forumaker-friendship');

        $this->prepareDatabase([
            'users' => [
                $this->normalUser(), // id 2
                ['id' => 3, 'username' => 'third', 'email' => 'third@machine.local', 'is_email_confirmed' => 1],
            ],
            'friendship_events' => [
                ['id' => 1, 'user_id' => 2, 'other_user_id' => 3, 'actor_id' => 2, 'action' => 'requested', 'created_at' => Carbon::now()->subDays(120)],
                ['id' => 2, 'user_id' => 3, 'other_user_id' => 2, 'actor_id' => 2, 'action' => 'requested', 'created_at' => Carbon::now()->subDays(120)],
                ['id' => 3, 'user_id' => 2, 'other_user_id' => 3, 'actor_id' => 2, 'action' => 'accepted', 'created_at' => Carbon::now()->subDays(10)],
            ],
        ]);
    }

    private function runCommand(): void
    {
        $command = $this->app()->getContainer()->make(PruneEventsCommand::class);
        $command->run(new ArrayInput([]), new NullOutput());
    }

    public function test_prunes_events_older_than_the_retention_window()
    {
        $this->setting('forumaker-friendship.event_retention_days', 90);

        $this->runCommand();

        $this->assertEquals(1, $this->database()->table('friendship_events')->count());
        $this->assertEquals(3, $this->database()->table('friendship_events')->first()->id);
    }

    public function test_retention_of_zero_disables_pruning()
    {
        $this->setting('forumaker-friendship.event_retention_days', 0);

        $this->runCommand();

        $this->assertEquals(3, $this->database()->table('friendship_events')->count());
    }
}
