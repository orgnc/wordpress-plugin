import PropTypes from 'prop-types';

export const AttributesType = PropTypes.shape({
  displayImage: PropTypes.bool.isRequired,
  displayDescription: PropTypes.bool.isRequired,
  productGuid: PropTypes.string.isRequired,
});
