const MAX_NUM_TRIES = 10;

const runAffiliateSDKOnPostLoad = (tryCount) => {
  if (tryCount < 1) {
    return;
  }
  const init = window.empire?.apps?.affiliate?.init;
  const integrations = document.querySelectorAll('[data-organic-affiliate-integration]');
  if (init !== undefined && integrations?.length > 0) {
    init();
  } else {
    setTimeout(() => runAffiliateSDKOnPostLoad(tryCount - 1), (MAX_NUM_TRIES - tryCount + 1) * 100);
  }
};

runAffiliateSDKOnPostLoad(MAX_NUM_TRIES);
