import { forwardRef } from '@wordpress/element';
import PropTypes from 'prop-types';

const ProductCard = forwardRef(({
  productCardSnippet,
}, ref) => {
  return (
    <div
      dangerouslySetInnerHTML={{ __html: productCardSnippet }} // eslint-disable-line react/no-danger
      ref={ref}
    />
  );
});

ProductCard.propTypes = {
  productCardSnippet: PropTypes.string.isRequired,
};

export default ProductCard;
