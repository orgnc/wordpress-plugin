import { forwardRef } from '@wordpress/element';
import PropTypes from 'prop-types';

function serializeOptions(options) {
  return Object.entries(options).map(([key, value]) => `${key}=${value}`).join(',');
}

function convertColorValue(value) {
  return value?.startsWith('#') ? value.slice(1) : value;
}

const ProductCard = forwardRef(({
  productGuid,
  displayImage,
  displayDescription,
  cardRadius,
  cardShadow,
  textColor,
  linkColor,
  backgroundColor,
}, ref) => {
  if (!productGuid) {
    return null;
  }
  const options = serializeOptions({
    displayDescription,
    displayImage,
    cardRadius,
    cardShadow,
    textColor: convertColorValue(textColor),
    linkColor: convertColorValue(linkColor),
    backgroundColor: convertColorValue(backgroundColor),
  });
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
  cardRadius: PropTypes.bool,
  cardShadow: PropTypes.bool,
  textColor: PropTypes.string,
  linkColor: PropTypes.string,
  backgroundColor: PropTypes.string,
};

ProductCard.defaultProps = {
  productGuid: '',
  displayImage: true,
  displayDescription: true,
  cardRadius: true,
  cardShadow: true,
  textColor: '',
  linkColor: '',
  backgroundColor: '',
};

export default ProductCard;
