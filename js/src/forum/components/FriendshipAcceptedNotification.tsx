import app from 'flarum/forum/app';
import Notification from 'flarum/forum/components/Notification';
import type Mithril from 'mithril';
import type User from 'flarum/common/models/User';

export default class FriendshipAcceptedNotification extends Notification {
  icon(): string {
    return 'fas fa-user-check';
  }

  href(): string {
    const notification = this.attrs.notification;
    const user = (notification.subject() as User | false) || notification.fromUser();
    return user ? app.route.user(user) : '#';
  }

  content(): Mithril.Children {
    const notification = this.attrs.notification;
    const data = notification.content() as { acceptorName?: string } | null;
    const fromUser = notification.fromUser() || null;
    const name = fromUser?.displayName() ?? data?.acceptorName ?? '?';

    return app.translator.trans('forumaker-friendship.forum.notifications.accepted_text', {
      name: <strong>{name}</strong>,
    });
  }

  excerpt(): Mithril.Children {
    return null;
  }
}
