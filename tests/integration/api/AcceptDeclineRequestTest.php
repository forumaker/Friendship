<?php

declare(strict_types=1);

namespace forumaker\Friendship\Tests\integration\api;

use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;

class AcceptDeclineRequestTest extends TestCase
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
            'friendship_requests' => [
                ['id' => 1, 'sender_id' => 2, 'recipient_id' => 3, 'created_at' => date('Y-m-d H:i:s')],
            ],
            'group_user' => [
                ['user_id' => 4, 'group_id' => 3],
            ],
        ]);
    }

    public function test_recipient_can_accept_a_request()
    {
        $response = $this->send(
            $this->request('POST', '/api/friendship-requests/1/accept', ['authenticatedAs' => 3])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(0, $this->database()->table('friendship_requests')->count());

        $this->assertNotNull($this->database()->table('friendships')->where('user_id', 2)->where('friend_id', 3)->first());
        $this->assertNotNull($this->database()->table('friendships')->where('user_id', 3)->where('friend_id', 2)->first());

        $this->assertEquals(2, $this->database()->table('friendship_events')->count());
    }

    public function test_recipient_can_decline_a_request_and_sender_is_notified()
    {
        $response = $this->send(
            $this->request('POST', '/api/friendship-requests/1/decline', ['authenticatedAs' => 3])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(0, $this->database()->table('friendship_requests')->count());
        $this->assertEquals(0, $this->database()->table('friendships')->count());

        $notification = $this->database()->table('notifications')
            ->where('type', 'friendshipDeclined')
            ->where('user_id', 2)
            ->where('from_user_id', 3)
            ->first();
        $this->assertNotNull($notification);
    }

    public function test_a_third_party_without_moderate_permission_cannot_accept()
    {
        $response = $this->send(
            $this->request('POST', '/api/friendship-requests/1/accept', ['authenticatedAs' => 4])
        );

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals(1, $this->database()->table('friendship_requests')->count());
    }

    public function test_moderator_can_accept_on_behalf()
    {
        $this->database()->table('group_permission')->insert([
            'group_id' => 3, // reuse Member group for user 4, granting it moderate for this assertion
            'permission' => 'friendship.moderate',
        ]);

        $response = $this->send(
            $this->request('POST', '/api/friendship-requests/1/accept', ['authenticatedAs' => 4])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(2, $this->database()->table('friendships')->count());
    }

    public function test_sender_can_cancel_their_own_pending_request()
    {
        $response = $this->send(
            $this->request('DELETE', '/api/friendship-requests/1', ['authenticatedAs' => 2])
        );

        $this->assertEquals(204, $response->getStatusCode());
        $this->assertEquals(0, $this->database()->table('friendship_requests')->count());

        // Cancelling is silent — no notification for the recipient.
        $this->assertEquals(0, $this->database()->table('notifications')->count());
    }
}
