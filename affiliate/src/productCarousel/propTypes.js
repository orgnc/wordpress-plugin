import PropTypes from 'prop-types';

export const AttributesType = PropTypes.shape({
  productCarouselURL: PropTypes.string.isRequired,
  productCarouselSnippet: PropTypes.bool.isRequired,
});
