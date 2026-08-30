import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import Link from 'flarum/common/components/Link';
import type User from 'flarum/common/models/User';

/**
 * Friend-count badge next to the author's name in a post's header — same
 * `userViewItems` extension point PostUser exposes for Arena's stats
 * badge, so both extensions' badges sit in the same row.
 */
export default function addFriendBadgeToPost(): void {
  extend('flarum/forum/components/PostUser' as any, 'userViewItems', function (items: any, user: User) {
    if (!user) return;

    if (app.forum.attribute('friendshipShowBadgeOnPost') === false) return;
    if (user.attribute('friendshipShowInPosts') === false) return;

    const count = (user.attribute('friendCount') as number) || 0;
    if (count === 0) return;

    const color = (app.forum.attribute('friendshipBadgeColor') as string) || '#84DCC6';
    const icon = (app.forum.attribute('friendshipBadgeIcon') as string) || 'fas fa-clipboard-user';

    items.add(
      'friendCount',
      <Link href={app.route('user.friendship', { username: user.username() })} className="FriendshipPostBadge" style={{ color }}>
        <i className={icon} />
        <span className="FriendshipPostBadge-count">{count}</span>
      </Link>,
      80
    );
  });
}
