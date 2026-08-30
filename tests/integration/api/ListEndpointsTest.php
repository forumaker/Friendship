<?php

declare(strict_types=1);

namespace forumaker\Friendship\Tests\integration\api;

use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Covers the Index (list) endpoints' filter[x] handling — these go through
 * Flarum's SearchManager (see Search\FriendshipRequestSearcher and its
 * sibling searchers/filters), a completely different code path from the
 * plain scope()-based restriction used by Show/Delete. A regression here
 * previously slipped past every other test in this suite (none of them
 * exercise GET with a filter param) and only surfaced as a real 500 during
 * manual QA — see the Search\Filter\* docblocks for why.
 */
class ListEndpointsTest extends TestCase
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
            'friendship_requests' => [
                ['id' => 1, 'sender_id' => 2, 'recipient_id' => 3, 'created_at' => date('Y-m-d H:i:s')],
            ],
            'friendships' => [
                ['id' => 1, 'user_id' => 2, 'friend_id' => 3, 'created_at' => date('Y-m-d H:i:s')],
                ['id' => 2, 'user_id' => 3, 'friend_id' => 2, 'created_at' => date('Y-m-d H:i:s')],
            ],
            'friendship_events' => [
                ['id' => 1, 'user_id' => 2, 'other_user_id' => 3, 'actor_id' => 2, 'action' => 'requested', 'created_at' => date('Y-m-d H:i:s')],
            ],
        ]);
    }

    /**
     * The test harness's synthetic ServerRequest doesn't parse a query
     * string embedded in the path into getQueryParams() the way a real HTTP
     * server would — withQueryParams() has to be applied explicitly.
     */
    private function withFilter(ServerRequestInterface $request, array $filter): ServerRequestInterface
    {
        return $request->withQueryParams(['filter' => $filter]);
    }

    public function test_incoming_and_outgoing_request_filters_work()
    {
        $incoming = $this->send($this->withFilter(
            $this->request('GET', '/api/friendship-requests', ['authenticatedAs' => 3]),
            ['incoming' => 3]
        ));
        $this->assertEquals(200, $incoming->getStatusCode());
        $body = json_decode($incoming->getBody()->__toString(), true);
        $this->assertCount(1, $body['data']);

        $outgoing = $this->send($this->withFilter(
            $this->request('GET', '/api/friendship-requests', ['authenticatedAs' => 2]),
            ['outgoing' => 2]
        ));
        $this->assertEquals(200, $outgoing->getStatusCode());
        $body = json_decode($outgoing->getBody()->__toString(), true);
        $this->assertCount(1, $body['data']);
    }

    public function test_friendship_requests_index_without_a_filter_is_rejected()
    {
        $response = $this->send($this->request('GET', '/api/friendship-requests', ['authenticatedAs' => 2]));
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_a_user_cannot_list_someone_elses_incoming_requests_without_moderate()
    {
        $response = $this->send($this->withFilter(
            $this->request('GET', '/api/friendship-requests', ['authenticatedAs' => 2]),
            ['incoming' => 3]
        ));
        $this->assertEquals(403, $response->getStatusCode());
    }

    /**
     * Doesn't also assert the "someone else, no permission" denial here:
     * friendship.viewOthers defaults to the Member group, which every
     * authenticated user gets merged in implicitly (User::permissionGroupIds()
     * always includes Group::MEMBER_ID), so there's no clean "authenticated
     * but lacking it" user to test against without revoking that grant
     * mid-test — and User::$permissionCache is a process-static cache keyed
     * by group id set that a mid-test revoke doesn't invalidate (a
     * PHPUnit-process artifact, not a real staleness bug: each real HTTP
     * request is its own process). test_history_filter_by_user_requires_
     * moderate_for_others below exercises the identical enforcement path —
     * friendship.moderate isn't granted by default to anything user 3
     * implicitly belongs to, so it doesn't hit the same problem.
     */
    public function test_friendships_filter_by_user_returns_that_users_friends()
    {
        $own = $this->send($this->withFilter(
            $this->request('GET', '/api/friendships', ['authenticatedAs' => 2]),
            ['user' => 2]
        ));
        $this->assertEquals(200, $own->getStatusCode());
        $body = json_decode($own->getBody()->__toString(), true);
        $this->assertCount(1, $body['data']);
    }

    public function test_friendships_index_without_a_filter_is_rejected()
    {
        $response = $this->send($this->request('GET', '/api/friendships', ['authenticatedAs' => 2]));
        $this->assertEquals(403, $response->getStatusCode());
    }

    /**
     * filter[-user]=<id> ("not this user") would otherwise return every
     * friendship on the forum that isn't this one user's — far wider than
     * the single-user grant filter[user] is meant to allow. Rejected
     * unconditionally regardless of the actor's own id/permissions.
     */
    public function test_friendships_negated_user_filter_is_rejected()
    {
        $response = $this->send($this->withFilter(
            $this->request('GET', '/api/friendships', ['authenticatedAs' => 2]),
            ['-user' => 2]
        ));
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_friendship_requests_negated_filter_is_rejected()
    {
        $incoming = $this->send($this->withFilter(
            $this->request('GET', '/api/friendship-requests', ['authenticatedAs' => 3]),
            ['-incoming' => 3]
        ));
        $this->assertEquals(403, $incoming->getStatusCode());

        $outgoing = $this->send($this->withFilter(
            $this->request('GET', '/api/friendship-requests', ['authenticatedAs' => 2]),
            ['-outgoing' => 2]
        ));
        $this->assertEquals(403, $outgoing->getStatusCode());
    }

    public function test_friendship_events_negated_filter_is_rejected()
    {
        $response = $this->send($this->withFilter(
            $this->request('GET', '/api/friendship-events', ['authenticatedAs' => 2]),
            ['-user' => 2]
        ));
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_history_filter_by_user_requires_moderate_for_others()
    {
        $own = $this->send($this->withFilter(
            $this->request('GET', '/api/friendship-events', ['authenticatedAs' => 2]),
            ['user' => 2]
        ));
        $this->assertEquals(200, $own->getStatusCode());
        $body = json_decode($own->getBody()->__toString(), true);
        $this->assertCount(1, $body['data']);

        // friendship.viewOthers is NOT enough for history — only moderate/manage.
        $forbidden = $this->send($this->withFilter(
            $this->request('GET', '/api/friendship-events', ['authenticatedAs' => 3]),
            ['user' => 2]
        ));
        $this->assertEquals(403, $forbidden->getStatusCode());
    }

    public function test_search_filter_matches_the_other_partys_username()
    {
        // Actor 3's incoming request is from user 2 ("normal") — matching
        // that username should find it regardless of which side of the pair
        // "the other party" ends up on.
        $match = $this->send($this->withFilter(
            $this->request('GET', '/api/friendship-requests', ['authenticatedAs' => 3]),
            ['incoming' => 3, 'q' => 'norm']
        ));
        $this->assertEquals(200, $match->getStatusCode());
        $body = json_decode($match->getBody()->__toString(), true);
        $this->assertCount(1, $body['data']);

        $noMatch = $this->send($this->withFilter(
            $this->request('GET', '/api/friendship-requests', ['authenticatedAs' => 3]),
            ['incoming' => 3, 'q' => 'nobody-has-this-name']
        ));
        $this->assertEquals(200, $noMatch->getStatusCode());
        $body = json_decode($noMatch->getBody()->__toString(), true);
        $this->assertCount(0, $body['data']);
    }

    public function test_friendships_search_filter_matches_the_friends_username()
    {
        // User 2 and 3 are already friends (setUp) — searching user 2's
        // friend list for "third" should find the row, matching on the
        // `friend` relation only.
        $match = $this->send($this->withFilter(
            $this->request('GET', '/api/friendships', ['authenticatedAs' => 2]),
            ['user' => 2, 'q' => 'third']
        ));
        $this->assertEquals(200, $match->getStatusCode());
        $body = json_decode($match->getBody()->__toString(), true);
        $this->assertCount(1, $body['data']);

        $noMatch = $this->send($this->withFilter(
            $this->request('GET', '/api/friendships', ['authenticatedAs' => 2]),
            ['user' => 2, 'q' => 'nobody-has-this-name']
        ));
        $this->assertEquals(200, $noMatch->getStatusCode());
        $body = json_decode($noMatch->getBody()->__toString(), true);
        $this->assertCount(0, $body['data']);
    }
}
