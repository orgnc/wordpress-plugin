import PropTypes from 'prop-types';

export const AttributesType = PropTypes.shape({
  productCardEditURL: PropTypes.string,
  productCardSnippet: PropTypes.bool.isRequired,
});
