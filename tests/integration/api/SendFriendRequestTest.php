<?php

declare(strict_types=1);

namespace forumaker\Friendship\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;

class SendFriendRequestTest extends TestCase
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
            // Member group (id 3) is where friendship.addFriends is granted
            // by default (see the permissions migration) — raw-inserted test
            // users don't join it automatically the way registration does.
            'group_user' => [
                ['user_id' => 2, 'group_id' => 3],
            ],
        ]);
    }

    public function test_member_can_send_a_friend_request()
    {
        $response = $this->send(
            $this->request('POST', '/api/friendship-requests', [
                'authenticatedAs' => 2,
                'json' => ['recipientId' => 3],
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody()->__toString(), true);
        $this->assertEquals('requested', $body['status']);

        $row = $this->database()->table('friendship_requests')->where('sender_id', 2)->where('recipient_id', 3)->first();
        $this->assertNotNull($row);

        $notification = $this->database()->table('notifications')
            ->where('type', 'friendshipRequested')
            ->where('user_id', 3)
            ->where('from_user_id', 2)
            ->first();
        $this->assertNotNull($notification);
    }

    public function test_cannot_send_a_friend_request_to_yourself()
    {
        $response = $this->send(
            $this->request('POST', '/api/friendship-requests', [
                'authenticatedAs' => 2,
                'json' => ['recipientId' => 2],
            ])
        );

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertEquals(0, $this->database()->table('friendship_requests')->count());
    }

    public function test_mutual_request_auto_accepts_instead_of_creating_a_duplicate_row()
    {
        // 3 already asked 2 first.
        $this->database()->table('friendship_requests')->insert([
            'sender_id' => 3,
            'recipient_id' => 2,
            'created_at' => Carbon::now(),
        ]);

        $response = $this->send(
            $this->request('POST', '/api/friendship-requests', [
                'authenticatedAs' => 2,
                'json' => ['recipientId' => 3],
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody()->__toString(), true);
        $this->assertEquals('auto_accepted', $body['status']);

        $this->assertEquals(0, $this->database()->table('friendship_requests')->count());
        $this->assertEquals(2, $this->database()->table('friendships')->count());

        $forward = $this->database()->table('friendships')->where('user_id', 2)->where('friend_id', 3)->first();
        $backward = $this->database()->table('friendships')->where('user_id', 3)->where('friend_id', 2)->first();
        $this->assertNotNull($forward);
        $this->assertNotNull($backward);
    }

    public function test_cannot_send_a_friend_request_without_permission()
    {
        $this->database()->table('group_permission')->where('permission', 'friendship.addFriends')->delete();

        $response = $this->send(
            $this->request('POST', '/api/friendship-requests', [
                'authenticatedAs' => 2,
                'json' => ['recipientId' => 3],
            ])
        );

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_guest_cannot_send_a_friend_request()
    {
        // No 'authenticatedAs' means no CSRF token either, so this trips
        // CheckCsrfToken (400) before ever reaching the permission check —
        // still "cannot", just earlier in the pipeline than a logged-in
        // member without the permission (see test_cannot_send_a_friend_
        // request_without_permission, which gets a real 403).
        $response = $this->send(
            $this->request('POST', '/api/friendship-requests', [
                'json' => ['recipientId' => 3],
            ])
        );

        $this->assertEquals(400, $response->getStatusCode());
    }
}
