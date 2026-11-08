const html = document.documentElement;

/**
 * Takes the loading screen down once the first page has been drawn under it.
 * The initializer runs before the forum mounts, so this waits for the page's
 * content to appear (never longer than three seconds), lets one frame paint
 * it, then fades the screen out.
 */
export default function liftCurtain() {
  if (!html.classList.contains('segue-loading')) return;

  const started = Date.now();

  const check = () => {
    const content = document.getElementById('content');

    if (content?.childElementCount || Date.now() - started > 3000) {
      requestAnimationFrame(() => {
        html.classList.add('segue-ready');
        setTimeout(() => html.classList.remove('segue-loading', 'segue-ready'), 350);
      });
    } else {
      setTimeout(check, 16);
    }
  };

  check();
}
