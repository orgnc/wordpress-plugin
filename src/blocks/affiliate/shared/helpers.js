import { select, subscribe } from '@wordpress/data';

// See https://gist.github.com/KevinBatdorf/fca19e1f3b749b5c57db8158f4850eff
async function whenEditorIsReady() {
  return new Promise((resolve) => {
    const unsubscribe = subscribe(() => {
      // This will trigger after the initial render blocking, before the window load event
      // This seems currently more reliable than using __unstableIsEditorReady
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
  whenEditorIsReady().then(() => {
    if (!window.wp_organic_affiliate_processed) {
      window.wp_organic_affiliate_processed = true;
      refreshAffiliateWidgets();
    }
  });
};
