import app from 'flarum/forum/app';
import Modal, { IInternalModalAttrs } from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Avatar from 'flarum/common/components/Avatar';
import humanTime from 'flarum/common/helpers/humanTime';
import type Mithril from 'mithril';
import type User from 'flarum/common/models/User';
import type { FriendshipEvent } from '../../common';

interface HistoryModalAttrs extends IInternalModalAttrs {
  user: User;
}

function historyText(event: FriendshipEvent, viewedUser: User): Mithril.Children {
  const actor = event.actor();
  const other = event.otherUser();
  if (!other) return '?';

  const isSelf = actor && viewedUser && actor.id() === viewedUser.id();
  const key = `forumaker-friendship.forum.history.${event.action()}_${isSelf ? 'self' : 'other'}`;

  // Raw User model under `user` — Translator.preprocessParameters() special-cases
  // that exact key and derives a `{username}` placeholder from it internally
  // (see AddFriendConfirmModal's identical comment for the full explanation).
  return app.translator.trans(key, { user: other });
}

export default class HistoryModal extends Modal<HistoryModalAttrs> {
  loading: boolean = true;
  loadingMore: boolean = false;
  events: FriendshipEvent[] = [];
  hasMore: boolean = false;

  className() {
    return 'FriendshipHistoryModal Modal--medium';
  }

  title(): Mithril.Children {
    return (
      <>
        <i className="fas fa-history" /> {app.translator.trans('forumaker-friendship.forum.history_modal.title')}
      </>
    );
  }

  oncreate(vnode: Mithril.VnodeDOM<HistoryModalAttrs, this>) {
    super.oncreate(vnode);
    this.load();
  }

  async load(loadMore: boolean = false): Promise<void> {
    if (loadMore) {
      this.loadingMore = true;
    } else {
      this.loading = true;
      this.events = [];
    }
    m.redraw();

    try {
      const response = await app.request<any>({
        method: 'GET',
        url: `${app.forum.attribute('apiUrl')}/friendship-events`,
        params: {
          'filter[user]': this.attrs.user.id(),
          include: 'otherUser,actor',
          'page[offset]': loadMore ? this.events.length : 0,
          'page[limit]': 20,
        },
      });

      (response.included || []).forEach((inc: any) => app.store.pushObject(inc));
      const newEvents = response.data.map((d: any) => app.store.pushObject(d)) as FriendshipEvent[];

      this.events = loadMore ? [...this.events, ...newEvents] : newEvents;
      this.hasMore = newEvents.length >= 20;
    } finally {
      this.loading = false;
      this.loadingMore = false;
      m.redraw();
    }
  }

  content(): Mithril.Children {
    if (this.loading) {
      return (
        <div className="Modal-body">
          <LoadingIndicator />
        </div>
      );
    }

    return (
      <div className="Modal-body">
        {this.events.length === 0 ? (
          <p className="FriendshipHistoryModal-empty">{app.translator.trans('forumaker-friendship.forum.history_modal.empty')}</p>
        ) : (
          <ul className="FriendshipHistoryModal-list">
            {this.events.map((event) => {
              const other = event.otherUser();
              return (
                <li className="FriendshipHistoryModal-item" key={event.id()}>
                  {other && <Avatar user={other} />}
                  <div className="FriendshipHistoryModal-itemContent">
                    <span className="FriendshipHistoryModal-text">{historyText(event, this.attrs.user)}</span>
                    <span className="FriendshipHistoryModal-date">{humanTime(event.createdAt())}</span>
                  </div>
                </li>
              );
            })}
          </ul>
        )}
        {this.hasMore && (
          <div className="FriendshipHistoryModal-loadMore">
            <Button className="Button" loading={this.loadingMore} onclick={() => this.load(true)}>
              {app.translator.trans('forumaker-friendship.forum.history_modal.load_more')}
            </Button>
          </div>
        )}
      </div>
    );
  }
}
