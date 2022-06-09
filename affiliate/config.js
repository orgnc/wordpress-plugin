/* eslint-disable camelcase, no-undef */
window.empire ||= {};
window.empire.siteGuid = organic_sdk_config.siteGuid;
window.empire.apps ||= {};
window.empire.apps.affiliate ||= {};
window.empire.apps.affiliate.config = {
  siteConf: {
    guid: organic_sdk_config.siteGuid,
    publicDomain: organic_sdk_config.publicDomain,
  },
  appConf: {
    linkDomains: organic_sdk_config.linkDomains,
    trackingIDs: {
      default: {
        amazonUS: organic_sdk_config.amazonUsDefault,
      },
      recovery: {
        amazonUS: organic_sdk_config.amazonUsRecovery,
      },
    },
    logEvents: Boolean(organic_sdk_config.logEvents),
  },
};
