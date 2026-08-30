<?php

declare(strict_types=1);

namespace forumaker\Friendship\Tests\integration\api;

use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;

class RemoveFriendTest extends TestCase
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
                ['id' => 4, 'username' => 'fourth', 'email' => 'fourth@machine.local', 'is_email_confirmed' => 1],
            ],
            'friendships' => [
                ['id' => 1, 'user_id' => 2, 'friend_id' => 3, 'created_at' => date('Y-m-d H:i:s')],
                ['id' => 2, 'user_id' => 3, 'friend_id' => 2, 'created_at' => date('Y-m-d H:i:s')],
            ],
        ]);
    }

    public function test_a_party_can_remove_the_friendship_and_the_other_party_is_notified()
    {
        $response = $this->send(
            $this->request('DELETE', '/api/friendships/1', ['authenticatedAs' => 2])
        );

        $this->assertEquals(204, $response->getStatusCode());

        // Both symmetric rows are gone, not just the one that was deleted.
        $this->assertEquals(0, $this->database()->table('friendships')->count());

        $notification = $this->database()->table('notifications')
            ->where('type', 'friendshipRemoved')
            ->where('user_id', 3)
            ->where('from_user_id', 2)
            ->first();
        $this->assertNotNull($notification);

        $events = $this->database()->table('friendship_events')->get();
        $this->assertCount(2, $events);
    }

    public function test_the_dropdown_removal_endpoint_works_the_same_way()
    {
        $response = $this->send(
            $this->request('POST', '/api/friendships/remove', [
                'authenticatedAs' => 3,
                'json' => ['userId' => 2],
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(0, $this->database()->table('friendships')->count());
    }

    public function test_an_unrelated_user_without_moderate_permission_cannot_remove_it()
    {
        $response = $this->send(
            $this->request('DELETE', '/api/friendships/1', ['authenticatedAs' => 4])
        );

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals(2, $this->database()->table('friendships')->count());
    }
}
