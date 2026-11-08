import app from 'flarum/admin/app';
import IconPicker from './components/IconPicker';

const KEY = 'ernestdefoe-segue.';

app.initializers.add('ernestdefoe-segue', () => {
  const t = (key) => app.translator.trans('ernestdefoe-segue.admin.' + key);
  const options = (key, values) => Object.fromEntries(values.map((value) => [value, t(key + '.' + value)]));

  app.registry
    .for('ernestdefoe-segue')
    .registerSetting({
      setting: KEY + 'loader',
      type: 'switch',
      label: t('loader_label'),
      help: t('loader_help'),
    })
    .registerSetting(function () {
      return <IconPicker icon={this.setting(KEY + 'icon', 'ring')} color={this.setting(KEY + 'color')} />;
    })
    .registerSetting({
      setting: KEY + 'size',
      type: 'select',
      label: t('size_label'),
      options: options('size', ['small', 'medium', 'large']),
      default: 'medium',
    })
    .registerSetting({
      setting: KEY + 'color',
      type: 'color-preview',
      label: t('color_label'),
      help: t('color_help'),
    })
    .registerSetting({
      setting: KEY + 'transitions',
      type: 'select',
      label: t('transitions_label'),
      help: t('transitions_help'),
      options: options('transitions', ['glide', 'fade', 'off']),
      default: 'glide',
    })
    .registerSetting({
      setting: KEY + 'speed',
      type: 'select',
      label: t('speed_label'),
      options: options('speed', ['fast', 'normal', 'slow']),
      default: 'normal',
    })
    .registerSetting({
      setting: KEY + 'full_page',
      type: 'switch',
      label: t('full_page_label'),
      help: t('full_page_help'),
    });
});
