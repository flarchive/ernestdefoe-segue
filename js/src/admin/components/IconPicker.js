import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import Icon from 'flarum/common/components/Icon';

const BUILT_IN = ['ring', 'dots', 'bars', 'pulse', 'orbit', 'logo'];
const ICONS = 'ernestdefoe-segue.icons';

/**
 * The loading icon setting: a tile for every icon, each one running, with
 * the chosen one outlined. Admins add their own icons from the last tile;
 * uploads are saved at once (the choice itself waits for Save Changes).
 *
 * attrs.icon and attrs.color are the page's setting streams.
 */
export default class IconPicker extends Component {
  oninit(vnode) {
    super.oninit(vnode);
    this.uploading = false;
  }

  view() {
    const t = (key, params) => app.translator.trans('ernestdefoe-segue.admin.icons.' + key, params);
    const chosen = this.attrs.icon();
    const color = this.attrs.color();
    const style = /^#(?:[0-9a-f]{3}){1,2}$/i.test(color || '') ? { '--segue-color': color } : {};
    const logo = app.forum.attribute('logoUrl');
    const uploads = this.uploads();

    const tile = (value, label, preview, extra) => (
      <div className="SegueIcons-cell">
        <button
          type="button"
          className={'SegueIcons-tile' + (chosen === value ? ' is-active' : '')}
          aria-pressed={chosen === value ? 'true' : 'false'}
          onclick={() => this.attrs.icon(value)}
        >
          <span className="SegueIcons-preview" style={style}>
            {preview}
          </span>
          <span className="SegueIcons-label">{label}</span>
        </button>
        {extra}
      </div>
    );

    return (
      <div className="Form-group SegueIconsGroup">
        <label>{t('label')}</label>
        <div className="helpText">{t('help')}</div>
        <div className="SegueIcons">
          {BUILT_IN.map((name) =>
            name === 'logo'
              ? logo && tile('logo', t('logo'), <span className="SegueIcon SegueIcon--logo" style={{ '--segue-image': cssUrl(logo) }} />)
              : tile(name, t(name), <span className={'SegueIcon SegueIcon--' + name} />)
          )}
          {uploads.map((name, i) =>
            tile(
              'file:' + name,
              t('custom', { number: i + 1 }),
              <span className="SegueIcon SegueIcon--image" style={{ '--segue-image': cssUrl(this.url(name)) }} />,
              <Button
                className="Button Button--icon Button--link SegueIcons-remove"
                icon="fas fa-times"
                aria-label={t('remove')}
                onclick={() => this.remove(name)}
              />
            )
          )}
          <div className="SegueIcons-cell">
            <label className={'SegueIcons-tile SegueIcons-upload' + (this.uploading ? ' disabled' : '')}>
              <span className="SegueIcons-preview">
                <Icon name={this.uploading ? 'fas fa-spinner fa-spin' : 'fas fa-upload'} />
              </span>
              <span className="SegueIcons-label">{t('upload')}</span>
              <input
                type="file"
                accept="image/gif,image/png,image/webp,image/jpeg"
                hidden
                disabled={this.uploading}
                onchange={(e) => this.upload(e.target)}
              />
            </label>
          </div>
        </div>
        <div className="helpText">{t('upload_help')}</div>
      </div>
    );
  }

  uploads() {
    try {
      return JSON.parse(app.data.settings[ICONS] || '[]');
    } catch {
      return [];
    }
  }

  url(name) {
    return app.forum.attribute('assetsBaseUrl') + '/segue/' + name;
  }

  upload(input) {
    const file = input.files[0];
    input.value = '';
    if (!file) return;

    const body = new FormData();
    body.append('icon', file);

    this.uploading = true;
    app
      .request({ method: 'POST', url: app.forum.attribute('apiUrl') + '/segue/icons', serialize: (raw) => raw, body })
      .then((response) => {
        this.saved(response.icons);
        // Picking what was just added is almost always the point of adding it.
        this.attrs.icon('file:' + response.added);
      })
      .finally(() => {
        this.uploading = false;
        m.redraw();
      });
  }

  remove(name) {
    app.request({ method: 'DELETE', url: app.forum.attribute('apiUrl') + '/segue/icons/' + name }).then((response) => {
      this.saved(response.icons);
      if (this.attrs.icon() === 'file:' + name) this.attrs.icon('ring');
      m.redraw();
    });
  }

  saved(icons) {
    app.data.settings[ICONS] = JSON.stringify(icons);
  }
}

// Our URLs never hold a quote, but keep url("") closed whatever they hold.
function cssUrl(url) {
  return 'url("' + String(url).replace(/["\\\n]/g, encodeURIComponent) + '")';
}
