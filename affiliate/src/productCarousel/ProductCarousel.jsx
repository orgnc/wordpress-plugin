import { forwardRef } from '@wordpress/element';
import PropTypes from 'prop-types';

const ProductCarousel = forwardRef(({
  productCarouselSnippet,
}, ref) => (
  <div
    dangerouslySetInnerHTML={productCarouselSnippet} // eslint-disable-line react/no-danger
    ref={ref}
  />
));

ProductCarousel.propTypes = {
  productCarouselSnippet: PropTypes.string.isRequired,
};

export default ProductCarousel;
