import app from 'flarum/admin/app';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';

const BADGE_COLOR_SETTING = 'forumaker-friendship.badge_color';
const BADGE_COLOR_DEFAULT = '#84DCC6';
const BADGE_ICON_SETTING = 'forumaker-friendship.badge_icon';
const BADGE_ICON_DEFAULT = 'fas fa-clipboard-user';

export default class FriendshipAdminPage extends ExtensionPage {
  content() {
    const colorStream = this.setting(BADGE_COLOR_SETTING, BADGE_COLOR_DEFAULT);
    const iconStream = this.setting(BADGE_ICON_SETTING, BADGE_ICON_DEFAULT);

    return (
      <div className="FriendshipAdminPage">
        <div className="FriendshipAdminPage-content">
          <div className="FriendshipAdminPage-section">
            <h3>
              <i className="fas fa-user-friends" aria-hidden="true" />
              {app.translator.trans('forumaker-friendship.admin.sections.badges')}
            </h3>
            <p className="helpText">{app.translator.trans('forumaker-friendship.admin.settings.badges_intro')}</p>

            <div className="Form-group">
              {this.buildSettingComponent({
                type: 'boolean',
                setting: 'forumaker-friendship.show_badge_on_usercard',
                label: app.translator.trans('forumaker-friendship.admin.settings.show_badge_on_usercard'),
                help: app.translator.trans('forumaker-friendship.admin.settings.show_badge_on_usercard_help'),
              })}
            </div>
            <div className="Form-group">
              {this.buildSettingComponent({
                type: 'boolean',
                setting: 'forumaker-friendship.show_badge_on_post',
                label: app.translator.trans('forumaker-friendship.admin.settings.show_badge_on_post'),
                help: app.translator.trans('forumaker-friendship.admin.settings.show_badge_on_post_help'),
              })}
            </div>
            <div className="Form-group">
              <label>{app.translator.trans('forumaker-friendship.admin.settings.badge_color')}</label>
              <div className="FriendshipAdminPage-colorRow">
                <input type="color" className="FriendshipAdminPage-colorInput" bidi={colorStream} />
                <input type="text" className="FormControl FriendshipAdminPage-colorHex" bidi={colorStream} />
                <input
                  type="text"
                  className="FormControl FriendshipAdminPage-iconInput"
                  placeholder={BADGE_ICON_DEFAULT}
                  bidi={iconStream}
                />
                <span className="FriendshipAdminPage-iconPreview">
                  <i className={iconStream() || BADGE_ICON_DEFAULT} style={{ color: colorStream() || BADGE_COLOR_DEFAULT }} />
                </span>
              </div>
            </div>
          </div>

          <div className="Form-group">{this.submitButton()}</div>
        </div>
      </div>
    );
  }
}
