import { select, subscribe } from '@wordpress/data';

// See https://gist.github.com/KevinBatdorf/fca19e1f3b749b5c57db8158f4850eff
async function whenEditorIsReady() {
  return new Promise((resolve) => {
    const unsubscribe = subscribe(() => {
      if (select('core/editor').isCleanNewPost() || select('core/block-editor').getBlockCount() > 0) {
        unsubscribe();
        resolve();
      }
    });
  });
}

const refreshAffiliateWidgets = () => {
  setTimeout(() => {
    window.organic ||= {};
    window.organic.cmd ||= [];
    window.organic.cmd.push(({ affiliate }) => affiliate?.processPage());
  }, 0);
};

export const refreshAffiliateWidgetsOnEdit = () => {
  refreshAffiliateWidgets();
};

export const refreshAffiliateWidgetsOnSave = () => {
  // Ideally, we wait for the Gutenberg editor to "be ready" and then refresh the display,
  // rendering our widget divs as iframed content. However, this has been difficult to
  // get working across browsers and WP environments.
  // TODO: Improve this!
  const refreshWidgetsIfNecessary = () => {
    // Each widget on the page will call this, so we put a check to only refresh once.
    // eslint-disable-next-line no-underscore-dangle
    if (!window.__wpOrganicAffiliateProcessed) {
      // eslint-disable-next-line no-underscore-dangle
      window.__wpOrganicAffiliateProcessed = true;
      refreshAffiliateWidgets();
    }
  };
  // A hacky backup in case whenEditorIsReady fails.
  setTimeout(() => refreshWidgetsIfNecessary(), 2000);
  // The below seems to work consistently Chromium browsers.
  whenEditorIsReady().then(() => refreshWidgetsIfNecessary());
};
