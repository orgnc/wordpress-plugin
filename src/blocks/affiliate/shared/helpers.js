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
  const refreshWidgetsIfNecessary = () => {
    // eslint-disable-next-line no-underscore-dangle
    if (!window.__wpOrganicAffiliateProcessed) {
      // eslint-disable-next-line no-underscore-dangle
      window.__wpOrganicAffiliateProcessed = true;
      refreshAffiliateWidgets();
    }
  };
  whenEditorIsReady().then(() => refreshWidgetsIfNecessary());
  // A hacky backup in case whenEditorIsReady fails.
  setTimeout(() => refreshWidgetsIfNecessary(), 500);
};
