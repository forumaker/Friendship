<?php

declare(strict_types=1);

namespace forumaker\Friendship;

use Flarum\Api\Resource;
use Flarum\Extend;
use Flarum\Search\Database\DatabaseSearchDriver;
use Flarum\User\User;

return [
    // Frontend
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),

    new Extend\Locales(__DIR__.'/locale'),

    // API Resources
    new Extend\ApiResource(Api\Resource\FriendshipRequestResource::class),
    new Extend\ApiResource(Api\Resource\FriendshipResource::class),
    new Extend\ApiResource(Api\Resource\FriendshipEventResource::class),

    // Search drivers — required for filter[x] on these resources' Index
    // endpoints. AbstractDatabaseResource::filters() always throws ("please
    // use a model searcher instead"), so any filter[] query param routes
    // through here (see the Search\Filter\* classes' docblocks) rather than
    // through Resource::scope().
    (new Extend\SearchDriver(DatabaseSearchDriver::class))
        ->addSearcher(FriendshipRequest::class, Search\FriendshipRequestSearcher::class)
        ->addFilter(Search\FriendshipRequestSearcher::class, Search\Filter\FriendshipRequestIncomingFilter::class)
        ->addFilter(Search\FriendshipRequestSearcher::class, Search\Filter\FriendshipRequestOutgoingFilter::class)
        ->addFilter(Search\FriendshipRequestSearcher::class, Search\Filter\FriendshipRequestSearchFilter::class)
        ->addSearcher(Friendship::class, Search\FriendshipSearcher::class)
        ->addFilter(Search\FriendshipSearcher::class, Search\Filter\FriendshipUserFilter::class)
        ->addFilter(Search\FriendshipSearcher::class, Search\Filter\FriendshipSearchFilter::class)
        ->addSearcher(FriendshipEvent::class, Search\FriendshipEventSearcher::class)
        ->addFilter(Search\FriendshipEventSearcher::class, Search\Filter\FriendshipEventUserFilter::class),

    // Custom (non-CRUD) endpoints — see FriendshipRequestResource's docblock
    // for why sending/accepting/declining aren't plain JSON:API actions.
    (new Extend\Routes('api'))
        ->post('/friendship-requests', 'friendship-requests.send', Api\Controller\SendFriendRequestController::class)
        ->post('/friendship-requests/{id}/accept', 'friendship-requests.accept', Api\Controller\AcceptFriendRequestController::class)
        ->post('/friendship-requests/{id}/decline', 'friendship-requests.decline', Api\Controller\DeclineFriendRequestController::class)
        ->post('/friendships/remove', 'friendships.remove', Api\Controller\RemoveFriendController::class),

    // User relationships used for N+1-safe friend counts (see
    // Api\UserResourceFields' countRelation('friendships')).
    (new Extend\Model(User::class))
        ->relationship('friendships', fn ($user) => $user->hasMany(Friendship::class, 'user_id'))
        ->relationship('friendshipEvents', fn ($user) => $user->hasMany(FriendshipEvent::class, 'user_id')),

    // Per-user badge visibility preferences — each only has an effect where
    // the matching admin-wide toggle is also on (see ForumResourceFields'
    // friendshipShowBadgeOnUserCard/OnPost and the Settings page toggles
    // that read them).
    (new Extend\User())
        ->registerPreference('friendshipShowOnUserCard', 'boolval', true)
        ->registerPreference('friendshipShowInPosts', 'boolval', true),

    // Forum resource fields (canX flags + admin badge-visibility settings)
    (new Extend\ApiResource(Resource\ForumResource::class))
        ->fields(Api\ForumResourceFields::class),

    // User resource fields (friendCount + actor-relative relationship state)
    (new Extend\ApiResource(Resource\UserResource::class))
        ->fields(Api\UserResourceFields::class),

    // Notifications
    (new Extend\Notification())
        ->type(Notification\FriendshipRequestedBlueprint::class, ['alert'])
        ->type(Notification\FriendshipRemovedBlueprint::class, ['alert'])
        ->type(Notification\FriendshipDeclinedBlueprint::class, ['alert']),

    // Settings defaults — badge visibility + color toggles (see the admin page)
    (new Extend\Settings())
        ->default('forumaker-friendship.show_badge_on_usercard', true)
        ->default('forumaker-friendship.show_badge_on_post', true)
        ->default('forumaker-friendship.badge_color', '#ffcb7f')
        ->default('forumaker-friendship.badge_bg_color', '#ffcb7f')
        ->default('forumaker-friendship.badge_icon', 'fas fa-clipboard-user'),

    // Model policies for authorization
    (new Extend\Policy())
        ->modelPolicy(FriendshipRequest::class, Access\FriendshipRequestPolicy::class)
        ->modelPolicy(Friendship::class, Access\FriendshipPolicy::class)
        ->modelPolicy(FriendshipEvent::class, Access\FriendshipEventPolicy::class),
];
