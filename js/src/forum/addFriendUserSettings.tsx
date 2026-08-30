import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import Switch from 'flarum/common/components/Switch';
import FieldSet from 'flarum/common/components/FieldSet';

/** "Друзья" section on the user's own Settings page — one toggle per badge, each only shown where the matching admin-wide toggle is on. */
export default function addFriendUserSettings(): void {
  extend('flarum/forum/components/SettingsPage' as any, 'settingsItems', function (items: any) {
    const user = (this as any).user;
    if (!user) return;

    const showOnUserCardAllowed = app.forum.attribute('friendshipShowBadgeOnUserCard') !== false;
    const showInPostsAllowed = app.forum.attribute('friendshipShowBadgeOnPost') !== false;

    if (!showOnUserCardAllowed && !showInPostsAllowed) return;

    const prefs = user.preferences?.() ?? {};
    const showOnUserCard = prefs['friendshipShowOnUserCard'] !== false;
    const showInPosts = prefs['friendshipShowInPosts'] !== false;

    items.add(
      'friendship',
      <FieldSet className="FriendshipSettings" label={app.translator.trans('forumaker-friendship.forum.settings.section_label')}>
        {showOnUserCardAllowed && (
          <Switch state={showOnUserCard} onchange={(value: boolean) => user.savePreferences({ friendshipShowOnUserCard: value })}>
            {app.translator.trans('forumaker-friendship.forum.settings.show_on_usercard')}
          </Switch>
        )}
        {showInPostsAllowed && (
          <Switch state={showInPosts} onchange={(value: boolean) => user.savePreferences({ friendshipShowInPosts: value })}>
            {app.translator.trans('forumaker-friendship.forum.settings.show_in_posts')}
          </Switch>
        )}
      </FieldSet>,
      -10
    );
  });
}
