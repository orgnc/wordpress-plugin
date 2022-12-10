import PropTypes from 'prop-types';

export const AttributesType = PropTypes.shape({
  bannerText: PropTypes.string.isRequired,
  displayImage: PropTypes.bool.isRequired,
  displayDescription: PropTypes.bool.isRequired,
  productGuid: PropTypes.string.isRequired,
  description: PropTypes.string,
});
