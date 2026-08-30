import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import UserCard from 'flarum/forum/components/UserCard';
import Link from 'flarum/common/components/Link';

export default function addFriendBadgeToUserCard(): void {
  extend(UserCard.prototype, 'infoItems', function (items) {
    const user = (this as any).attrs.user;
    if (!user) return;

    if (app.forum.attribute('friendshipShowBadgeOnUserCard') === false) return;
    if (user.attribute('friendshipShowOnUserCard') === false) return;

    const count = (user.attribute('friendCount') as number) || 0;
    if (count === 0) return;

    const color = (app.forum.attribute('friendshipBadgeColor') as string) || '#84DCC6';
    const icon = (app.forum.attribute('friendshipBadgeIcon') as string) || 'fas fa-clipboard-user';

    items.add(
      'friendCount',
      <Link href={app.route('user.friendship', { username: user.username() })} className="FriendshipBadge" style={{ color }}>
        <i className={icon} />
        <span className="FriendshipBadge-count">{count}</span>
      </Link>,
      12
    );
  });
}
