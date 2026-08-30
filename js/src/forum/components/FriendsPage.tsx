import app from 'flarum/forum/app';
import UserPage from 'flarum/forum/components/UserPage';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Button from 'flarum/common/components/Button';
import Link from 'flarum/common/components/Link';
import Avatar from 'flarum/common/components/Avatar';
import username from 'flarum/common/helpers/username';
import type Mithril from 'mithril';
import type User from 'flarum/common/models/User';
import type { Friendship } from '../../common';
import RequestsModal from './RequestsModal';
import RemoveFriendConfirmModal from './RemoveFriendConfirmModal';
import showFriendActionModal from '../showFriendActionModal';

const SEARCH_DEBOUNCE_MS = 700;

export default class FriendsPage extends UserPage {
  loading: boolean = true;
  loadingMore: boolean = false;
  friends: Friendship[] = [];
  totalCount: number = 0;
  hasMore: boolean = false;
  search: string = '';
  searchTimer: number | null = null;

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);
    this.loadUser(m.route.param('username'));
  }

  show(user: User): void {
    super.show(user);
    this.loadFriends();

    // Click-through from a "friendshipRequested" notification (see that
    // component's href()). Deferred: show() can run inside Mithril's initial
    // render pass, and app.modal.show() there throws "Node is currently
    // being rendered to and thus is locked".
    const requestId = m.route.param('requestId');
    if (requestId && app.session.user?.id() === user.id()) {
      setTimeout(() => this.showRequestsModal(String(requestId)), 0);
    }
  }

  onsearch(value: string): void {
    this.search = value;

    if (this.searchTimer) window.clearTimeout(this.searchTimer);
    this.searchTimer = window.setTimeout(() => this.loadFriends(), SEARCH_DEBOUNCE_MS);
  }

  async loadFriends(loadMore: boolean = false): Promise<void> {
    if (!this.user) return;

    if (loadMore) {
      this.loadingMore = true;
    } else {
      this.loading = true;
      this.friends = [];
    }
    m.redraw();

    try {
      const params: Record<string, unknown> = {
        'filter[user]': this.user.id(),
        include: 'friend',
        sort: '-createdAt',
        'page[offset]': loadMore ? this.friends.length : 0,
        'page[limit]': 24,
      };
      if (this.search.trim()) params['filter[q]'] = this.search.trim();

      const response = await app.request<any>({
        method: 'GET',
        url: `${app.forum.attribute('apiUrl')}/friendships`,
        params,
      });

      (response.included || []).forEach((inc: any) => app.store.pushObject(inc));
      const newFriends = response.data.map((d: any) => app.store.pushObject(d)) as Friendship[];

      this.friends = loadMore ? [...this.friends, ...newFriends] : newFriends;

      if (response.meta?.page?.total !== undefined) {
        this.totalCount = response.meta.page.total;
      } else if (response.meta?.total !== undefined) {
        this.totalCount = response.meta.total;
      } else if (!loadMore) {
        this.totalCount = this.friends.length;
      }

      this.hasMore = this.friends.length < this.totalCount;
    } catch (error) {
      if (!loadMore) {
        this.friends = [];
        this.totalCount = 0;
      }
    } finally {
      this.loading = false;
      this.loadingMore = false;
      m.redraw();
    }
  }

  content(): Mithril.Children {
    const isOwnPage = !!this.user && app.session.user?.id() === this.user.id();
    const canModerate = app.forum.attribute('canModerateFriendships') === true;
    const canOpenRequests = isOwnPage ? app.forum.attribute('canAddFriends') === true : canModerate;
    const canRemove = isOwnPage || canModerate;

    // Viewing someone else's page (via friendship.viewOthers/moderate):
    // the actor's own relationship to *that user* — not to their friends
    // listed below — same add/remove control as the profile dropdown.
    const showOwnRelationControl =
      !isOwnPage && !!this.user && !!app.session.user && (this.user.attribute('friendshipIsFriend') === true || app.forum.attribute('canAddFriends') === true);

    return (
      <div className="FriendsPage">
        <div className="FriendsPage-header">
          <h2>{app.translator.trans('forumaker-friendship.forum.user.friends_count', { count: this.totalCount })}</h2>
          <div className="FriendsPage-headerActions">
            {showOwnRelationControl && this.user && (
              <Button
                className="Button"
                icon={
                  this.user.attribute('friendshipIsFriend') === true
                    ? 'fas fa-user-minus'
                    : this.user.attribute('friendshipHasPendingOutgoing') === true
                      ? 'fas fa-ban'
                      : 'fas fa-user-plus'
                }
                onclick={() => showFriendActionModal(this.user!, { onChange: () => m.redraw() })}
              >
                {app.translator.trans(
                  this.user.attribute('friendshipIsFriend') === true
                    ? 'forumaker-friendship.forum.dropdown.remove_friend_btn'
                    : this.user.attribute('friendshipHasPendingOutgoing') === true
                      ? 'forumaker-friendship.forum.dropdown.cancel_request_btn'
                      : 'forumaker-friendship.forum.dropdown.add_friend_btn'
                )}
              </Button>
            )}
            {canOpenRequests && this.user && (
              <Button className="Button Button--primary" icon="fas fa-user-clock" onclick={() => this.showRequestsModal()}>
                {app.translator.trans('forumaker-friendship.forum.user.requests_btn')}
              </Button>
            )}
          </div>
        </div>

        <input
          type="text"
          className="FormControl FriendsPage-search"
          placeholder={String(app.translator.trans('forumaker-friendship.forum.requests_modal.search_placeholder'))}
          value={this.search}
          oninput={(e: InputEvent) => this.onsearch((e.target as HTMLInputElement).value)}
        />

        {this.loading ? (
          <LoadingIndicator />
        ) : this.friends.length === 0 ? (
          <div className="FriendsPage-emptyState">
            <i className="fas fa-user-friends FriendsPage-emptyIcon"></i>
            <p>{app.translator.trans('forumaker-friendship.forum.user.no_friends')}</p>
          </div>
        ) : (
          <ul className="FriendsPage-grid">
            {this.friends.map((friendship) => {
              const friend = friendship.friend();
              if (!friend) return null;

              return (
                <li className="FriendsPage-item" key={friendship.id()}>
                  <Link href={app.route.user(friend)} className="FriendsPage-itemLink">
                    <Avatar user={friend} />
                    <div className="FriendsPage-itemInfo">
                      <span className="FriendsPage-itemName">{username(friend)}</span>
                      <span className="FriendsPage-itemDate">
                        {app.translator.trans('forumaker-friendship.forum.user.friends_since', {
                          date: dayjs(friendship.createdAt()).format('D MMMM YYYY'),
                        })}
                      </span>
                    </div>
                  </Link>
                  {canRemove && (
                    <Button
                      className="Button Button--icon Button--flat FriendsPage-itemRemove"
                      icon="fas fa-user-minus"
                      onclick={() => app.modal.show(RemoveFriendConfirmModal, { user: friend, friendship, onRemoved: () => this.loadFriends() })}
                    />
                  )}
                </li>
              );
            })}
          </ul>
        )}

        {this.hasMore && (
          <div className="FriendsPage-loadMore">
            <Button className="Button" loading={this.loadingMore} onclick={() => this.loadFriends(true)}>
              {app.translator.trans('forumaker-friendship.forum.user.load_more')}
            </Button>
          </div>
        )}
      </div>
    );
  }

  showRequestsModal(highlightRequestId?: string): void {
    if (!this.user) return;

    app.modal.show(RequestsModal, {
      user: this.user,
      onChange: () => this.loadFriends(),
      highlightRequestId,
    });
  }
}
