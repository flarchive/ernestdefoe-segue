import app from 'flarum/forum/app';
import liftCurtain from './liftCurtain';
import pageTransitions from './pageTransitions';

app.initializers.add('ernestdefoe-segue', () => {
  liftCurtain();

  // app.forum exists from here, and the router (with its back/forward
  // listener) doesn't yet: pageTransitions needs both.
  app.beforeMount(pageTransitions);
});
