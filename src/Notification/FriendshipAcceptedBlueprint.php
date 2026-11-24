<?php

declare(strict_types=1);

namespace forumaker\Friendship\Notification;

use Flarum\Database\AbstractModel;
use Flarum\Notification\AlertableInterface;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\User\User;
use forumaker\Friendship\Support\UserDisplayHelper;

class FriendshipAcceptedBlueprint implements BlueprintInterface, AlertableInterface
{
    public function __construct(
        protected User $acceptor,
        protected int $requestId,
    ) {
    }

    public function getSubject(): ?AbstractModel
    {
        return $this->acceptor;
    }

    public function getFromUser(): ?User
    {
        return $this->acceptor;
    }

    public function getData(): mixed
    {
        return [
            'requestId' => $this->requestId,
            'acceptorName' => UserDisplayHelper::resolve($this->acceptor),
        ];
    }

    public static function getType(): string
    {
        return 'friendshipAccepted';
    }

    public static function getSubjectModel(): string
    {
        return User::class;
    }
}
