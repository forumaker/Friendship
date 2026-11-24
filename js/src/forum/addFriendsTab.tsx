import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import UserPage from 'flarum/forum/components/UserPage';
import LinkButton from 'flarum/common/components/LinkButton';
import type User from 'flarum/common/models/User';

export default function addFriendsTab(): void {
  extend(UserPage.prototype, 'navItems', function (items) {
    const user = (this as { user?: User }).user;
    if (!user) return;

    const isOwnPage = app.session.user?.id() === user.id();

    if (!isOwnPage && !app.forum.attribute('canViewOthersFriends') && !app.forum.attribute('canModerateFriendships')) {
      return;
    }

    const count = user.attribute<number>('friendCount');

    items.add(
      'friendship',
      <LinkButton href={app.route('user.friendship', { username: user.username() })} icon="fas fa-user-friends">
        {app.translator.trans('forumaker-friendship.forum.user.friends_tab')}{' '}
        {typeof count === 'number' && <span className="Button-badge">{count}</span>}
      </LinkButton>,
      65
    );
  });
}
