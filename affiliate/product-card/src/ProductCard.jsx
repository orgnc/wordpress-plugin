import PropTypes from 'prop-types';

function serializeOptions(options) {
  return Object.entries(options).map(([key, value]) => `${key}=${value}`).join(',');
}

function convertColorValue(value) {
  return value?.startsWith('#') ? value.slice(1) : value;
}

const ProductCard = ({
  productGuid,
  displayImage,
  displayDescription,
  textColor,
  linkColor,
  backgroundColor,
}) => {
  if (!productGuid) {
    return null;
  }
  const options = serializeOptions({
    displayDescription,
    displayImage,
    textColor: convertColorValue(textColor),
    linkColor: convertColorValue(linkColor),
    backgroundColor: convertColorValue(backgroundColor),
  });
  return (
    <div
      data-organic-affiliate-integration="product-card"
      data-organic-affiliate-integration-options={options}
      data-organic-affiliate-product-guid={productGuid}
    />
  );
};

ProductCard.propTypes = {
  productGuid: PropTypes.string,
  displayImage: PropTypes.bool,
  displayDescription: PropTypes.bool,
  textColor: PropTypes.string,
  linkColor: PropTypes.string,
  backgroundColor: PropTypes.string,
};

ProductCard.defaultProps = {
  productGuid: '',
  displayImage: true,
  displayDescription: true,
  textColor: '',
  linkColor: '',
  backgroundColor: '',
};

export default ProductCard;
