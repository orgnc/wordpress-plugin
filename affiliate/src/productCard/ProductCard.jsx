import { forwardRef } from '@wordpress/element';
import PropTypes from 'prop-types';

function serializeOptions(options) {
  return Object.entries(options).map(([key, value]) => `${key}=${value}`).join(',');
}

const ProductCard = forwardRef(({ productGuid, displayImage, displayDescription }, ref) => {
  if (!productGuid) {
    return null;
  }
  const options = serializeOptions({ displayDescription, displayImage });
  return (
    <div
      ref={ref}
      data-organic-affiliate-integration="product-card"
      data-organic-affiliate-integration-options={options}
      data-organic-affiliate-product-guid={productGuid}
    />
  );
});

ProductCard.propTypes = {
  productGuid: PropTypes.string,
  displayImage: PropTypes.bool,
  displayDescription: PropTypes.bool,
};

ProductCard.defaultProps = {
  productGuid: '',
  displayImage: true,
  displayDescription: false,
};

export default ProductCard;
