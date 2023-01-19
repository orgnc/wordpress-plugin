/*
Our widgets won't display properly in the editor unless the SDK is run.
This script is thus for initializing the affiliate SDK in the editor once the page is loaded.
 */
const waitForPageLoadAndInitSDK = (oldBlocks, remainingTries) => {
  if (remainingTries < 1) {
    return;
  }
  const newBlocks = document.querySelectorAll('[data-type]');
  if (newBlocks.length > 0 && newBlocks.length === oldBlocks.length) {
    const integrations = document.querySelectorAll('[data-organic-affiliate-integration]');
    if (integrations.length > 0) {
      window.empire?.apps?.affiliate?.init?.();
    }
  } else {
    setTimeout(() => waitForPageLoadAndInitSDK(newBlocks, remainingTries - 1), 500);
  }
};

waitForPageLoadAndInitSDK([], 20);
