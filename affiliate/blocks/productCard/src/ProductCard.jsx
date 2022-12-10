import { forwardRef } from '@wordpress/element';
import PropTypes from 'prop-types';

function serializeOptions(
    displayDescription,
    displayImage,
    description,
) {
  const filteredOptions = { displayDescription, displayImage };
  if (displayDescription) {
    filteredOptions.description = description;
  }
  return Object.entries(filteredOptions)
      .filter(([, value]) => (value !== '' && value != null))
      .map(([key, value]) => `${key}=${value}`).join(',');
}

const ProductCard = forwardRef(({
  productGuid,
  displayImage,
  displayDescription,
  bannerText,
  description,
}, ref) => {
  if (!productGuid) {
    return null;
  }
  const options = serializeOptions(displayDescription, displayImage, description);

  return (
    <div
      ref={ref}
      data-organic-affiliate-integration="product-card"
      data-organic-affiliate-integration-banner-text={bannerText}
      data-organic-affiliate-integration-options={options}
      data-organic-affiliate-product-guid={productGuid}
    />
  );
});

ProductCard.propTypes = {
  productGuid: PropTypes.string,
  displayImage: PropTypes.bool,
  displayDescription: PropTypes.bool,
  bannerText: PropTypes.string,
  description: PropTypes.string,
};

ProductCard.defaultProps = {
  productGuid: '',
  displayImage: true,
  displayDescription: false,
  bannerText: '',
  description: '',
};

export default ProductCard;
