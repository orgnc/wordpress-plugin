const handlePageLoad = () => {
  window.empire.cmd.push(({ affiliate, core }) => {
    // Core
    if (core && !core.isInitialized()) {
      core.init();
    }

    // Affiliate
    if (affiliate && affiliate?.isEnabled()) {
      if (!affiliate.isInitialized()) {
        affiliate.init();
      } else {
        affiliate.processPage();
      }
    }
  });
}

window.addEventListener('load', handlePageLoad);
