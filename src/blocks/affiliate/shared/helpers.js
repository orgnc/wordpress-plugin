export const refreshAffiliateWidgets = () => {
  setTimeout(() => {
    window.organic ||= {};
    window.organic.cmd ||= [];
    window.organic.cmd.push(({ affiliate }) => affiliate?.processPage());
  }, 0);
};
