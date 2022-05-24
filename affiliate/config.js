/* eslint-disable camelcase, no-undef */
window.empire ||= {};
window.empire.apps ||= {};
window.empire.apps.affiliate ||= {};
window.empire.apps.affiliate.config = {
  siteConf: {
    guid: organic_sdk_config.siteGuid,
    publicDomain: organic_sdk_config.publicDomain,
  },
  appConf: {
    linkDomains: organic_sdk_config.linkDomains,
    trackingIDs: {},
  },
};
