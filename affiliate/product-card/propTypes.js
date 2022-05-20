import PropTypes from 'prop-types';

export const AttributesType = PropTypes.shape({
  productGuid: PropTypes.string.isRequired,
  displayImage: PropTypes.bool.isRequired,
  displayDescription: PropTypes.bool.isRequired,
  textColor: PropTypes.string.isRequired,
  linkColor: PropTypes.string.isRequired,
  backgroundColor: PropTypes.string.isRequired,
});
