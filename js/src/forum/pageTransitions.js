import app from 'flarum/forum/app';

const html = document.documentElement;

// How long the old page may stay on screen while the new one finishes. The
// page itself must be drawn within ROUTE_WAIT; a discussion's title and
// avatar, which arrive with its posts, get PAIR_WAIT more before the move
// goes ahead as a plain fade.
const ROUTE_WAIT = 300;
const PAIR_WAIT = 450;

/**
 * Page transitions inside the forum, the same-document twin of the browser's
 * cross-page View Transitions. Every move between pages runs through
 * document.startViewTransition: the header stays still, the page fades and
 * rises, and when the move is between a discussion in a list and its page,
 * the title and the starter's avatar glide from one to the other.
 *
 * Only one title and one avatar ever carry a transition name, and only for
 * the length of a transition, so names never clash.
 */
export default function pageTransitions() {
  const style = app.forum.attribute('segueTransitions');
  const reduce = window.matchMedia('(prefers-reduced-motion: reduce)');

  if (style === 'off' || typeof document.startViewTransition !== 'function') return;

  html.setAttribute('data-segue-style', style);

  const glide = style === 'glide';
  let clicked = null;
  let clickedAt = 0;
  let replaying = false;
  // The page being shown. By the time a back or forward event arrives the
  // address already names the next page, so the one being left is kept here.
  let here = location.pathname;
  // Where the page was scrolled. On back and forward the browser jumps to
  // the next page's old scroll position before the event arrives, which
  // would leave the page being left photographed half-way down.
  let scrolled = window.scrollY;
  window.addEventListener('scroll', () => (scrolled = window.scrollY), { passive: true });

  // The element a navigation started from (an avatar, a list item). Only a
  // click in the last second counts, so a later move made by code (after a
  // reply is posted, say) isn't taken for one that started from it.
  document.addEventListener(
    'click',
    (e) => {
      clicked = e.target;
      clickedAt = Date.now();
    },
    true
  );

  const set = m.route.set;
  m.route.set = function (...args) {
    const source = Date.now() - clickedAt < 1000 ? clicked : null;
    clicked = null;

    const from = here;
    const go = () => {
      set.apply(this, args);
      here = location.pathname;
    };

    if (shouldAnimate(from, String(args[0]))) run(go, source, from);
    else go();
  };

  // Back and forward: hold Mithril's own listener back, then hand the event
  // on from inside the transition. This listener is added before the forum
  // mounts, so it runs first.
  window.addEventListener('popstate', (e) => {
    if (replaying) return;

    const from = here;
    here = location.pathname;

    if (!shouldAnimate(from, here)) return;

    e.stopImmediatePropagation();
    window.scrollTo(0, scrolled);
    run(
      () => {
        replaying = true;
        window.dispatchEvent(new PopStateEvent('popstate', { state: e.state }));
        replaying = false;
      },
      null,
      from
    );
  });

  function shouldAnimate(fromPath, to) {
    if (reduce.matches || document.hidden) return false;

    const from = pageKey(fromPath);
    const next = pageKey(to);

    // Moving within one discussion (a post link, the reply route) or only
    // changing the list's sort keeps the page where it is.
    return from.path !== next.path && !(from.id && from.id === next.id);
  }

  function run(navigate, source, from) {
    const before = app.current;
    const leaving = pageKey(from).key;
    // The pair either starts from what was clicked (and so belongs to the
    // page being opened) or stands for the page being left.
    const picked = glide && source ? fromSource(source) : null;
    const partner = picked ? null : leaving;
    const pair = glide ? mark(picked || byKey(leaving)) : null;

    html.classList.add('segue-vt');

    const transition = document.startViewTransition(async () => {
      unmark(pair);
      navigate();
      await drawn(before);

      if (!pair) return;

      // Name the same pair on the new page. A discussion or profile being
      // opened arrives with its data, so it gets a moment to show both
      // pieces; a list being returned to is already drawn.
      let found = null;

      if (partner) {
        found = byKey(partner);
      } else {
        const arriving = pageKey(location.pathname).key;
        const wait = arriving?.[0] === 't' ? 100 : PAIR_WAIT;
        if (arriving) await until(() => count((found = byKey(arriving, true))) >= pair.length, wait);
      }

      mark(found);
    });

    // A transition cut short by the next click rejects these; the move
    // itself has still happened.
    transition.ready.catch(() => {});
    transition.updateCallbackDone.catch(() => {});
    transition.finished
      .catch(() => {})
      .finally(() => {
        html.classList.remove('segue-vt');
        unmark(document.querySelectorAll('.segue-vt-title, .segue-vt-avatar'));
      });
  }

  // While a transition waits, the browser runs no animation frames, and
  // Mithril redraws on one. So the page is drawn here, by hand.
  async function drawn(before) {
    await until(() => {
      m.redraw.sync();
      return app.current !== before;
    }, ROUTE_WAIT);
  }

  function until(test, limit) {
    const started = Date.now();

    return new Promise((resolve) => {
      const check = () => {
        if (test() || Date.now() - started > limit) return resolve();
        m.redraw.sync();
        setTimeout(check, 30);
      };
      check();
    });
  }

  /**
   * Turns a path into what the page shows: a discussion (d:12), a tag
   * (t:general) or a member (u:alice), plus the bare path for comparisons.
   */
  function pageKey(url) {
    const base = app.forum.attribute('basePath') || '';
    const path = new URL(url, location.origin).pathname.slice(base.length).replace(/\/+$/, '') || '/';
    const match = path.match(/^\/(d|t|u)\/([^/]+)/);

    if (!match) return { path };

    const id = match[1] === 'd' ? match[2].split('-')[0] : decodeURIComponent(match[2]);

    return { path, id: match[1] === 'd' ? id : null, key: match[1] + ':' + id };
  }

  /**
   * The title and avatar a move starts from, found from what was clicked: a
   * discussion in a list, an avatar, a tag tile. Anything else (a header
   * link, say) gives null, and the move starts from the page being left.
   */
  function fromSource(source) {
    const item = source.closest('.DiscussionListItem');
    if (item && !source.closest('.DiscussionListItem-author-avatar')) return listItem(item);

    const avatar = source.closest('a')?.querySelector('.Avatar');
    if (avatar) return { avatar };

    const tag = source.closest('.TagTile');
    if (tag) return { title: tag.querySelector('.TagTile-name') };

    return null;
  }

  /**
   * The title and avatar standing for a page key on the page showing now.
   * `pageOnly` skips list items, for a discussion page whose own hero hasn't
   * arrived yet (its side list may hold the same discussion).
   */
  function byKey(key, pageOnly = false) {
    if (!key) return null;

    const kind = key[0];
    const id = key.slice(2);

    if (kind === 'd') {
      const hero = document.querySelector('.DiscussionHero-title');
      if (hero) {
        return {
          title: hero,
          avatar: firstVisible('.PostStream-item[data-number="1"] :is(.Post-avatar, .PostUser-avatar)'),
        };
      }

      if (pageOnly) return null;

      for (const link of document.querySelectorAll('.DiscussionListItem-main')) {
        if (pageKey(link.getAttribute('href')).id === id) return listItem(link.closest('.DiscussionListItem'));
      }
    }

    if (kind === 'u') return { avatar: firstVisible('.UserCard--profile .UserCard-avatar .Avatar') };

    if (kind === 't') {
      // Bespoke draws its own hero over the tag page.
      const hero = firstVisible('.TagHero .Hero-title, .HeroBanner-title');
      if (hero || pageOnly) return { title: hero };

      const tile = [...document.querySelectorAll('.TagTile-info')].find((link) => pageKey(link.getAttribute('href')).key === key);
      return { title: tile?.querySelector('.TagTile-name') };
    }

    return null;
  }

  function listItem(item) {
    return {
      title: item.querySelector('.DiscussionListItem-title'),
      avatar: item.querySelector('.DiscussionListItem-author .Avatar'),
    };
  }

  // Names the pair (only what is on screen), returning it, or null if
  // nothing could be named.
  function mark(pair) {
    if (!pair) return null;

    const named = [];
    if (visible(pair.title)) named.push(pair.title.classList.add('segue-vt-title') || pair.title);
    if (visible(pair.avatar)) named.push(pair.avatar.classList.add('segue-vt-avatar') || pair.avatar);

    return named.length ? named : null;
  }

  function count(pair) {
    return pair ? [pair.title, pair.avatar].filter(visible).length : 0;
  }

  function firstVisible(selector) {
    return [...document.querySelectorAll(selector)].find(visible) || null;
  }

  function unmark(elements) {
    for (const el of elements || []) el.classList.remove('segue-vt-title', 'segue-vt-avatar');
  }

  function visible(el) {
    if (!el) return false;
    const box = el.getBoundingClientRect();
    return box.width > 0 && box.bottom > 0 && box.top < innerHeight && box.right > 0 && box.left < innerWidth;
  }
}
