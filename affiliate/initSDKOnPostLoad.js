const handlePageLoad = () => {
  window.empire.cmd.push(({ affiliate, core }) => {
    // Core
    if (core && !core.isInitialized()) {
      core.init();
    }

    // Affiliate
    if (affiliate && affiliate?.isEnabled()) {
      if (!affiliate.isInitialized()) {
        setTimeout(() => affiliate.init(), 0);
      } else {
        setTimeout(() => affiliate.processPage(), 0);
      }
    }
  });
};

window.addEventListener('pageshow', handlePageLoad);
