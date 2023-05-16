export const refreshAffiliateWidgets = () => {
  window.organic ||= {};
  window.organic.cmd ||= [];
  window.organic.cmd.push(({ affiliate }) => affiliate?.processPage());
};
