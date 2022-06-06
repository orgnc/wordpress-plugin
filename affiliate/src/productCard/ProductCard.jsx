import { forwardRef } from '@wordpress/element';
import PropTypes from 'prop-types';

function serializeOptions(separator, options) {
  return Object.entries(options).map(([key, value]) => `${key}=${value}`).join(separator);
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
  isAmp,
  publicDomain,
}, ref) => {
  if (!productGuid) {
    return null;
  }
  const options = serializeOptions(
    isAmp ? '&' : ',',
    {
      displayDescription,
      displayImage,
      cardRadius,
      cardShadow,
      textColor: convertColorValue(textColor),
      linkColor: convertColorValue(linkColor),
      backgroundColor: convertColorValue(backgroundColor),
    },
  );
  if (!isAmp) {
    return (
      <div
        ref={ref}
        data-organic-affiliate-integration="product-card"
        data-organic-affiliate-integration-options={options}
        data-organic-affiliate-product-guid={productGuid}
      />
    );
  }
  const src = `${publicDomain}/integrations/affiliate/product-card?guid=${productGuid}&${options}`;
  return (
    <amp-iframe
      frameborder={0}
      height="540px"
      sandbox="allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox"
      src={src}
    >
      <p placeholder>Loading iframe content</p>
    </amp-iframe>
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
  isAmp: PropTypes.bool,
  publicDomain: PropTypes.string,
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
  isAmp: false,
  publicDomain: '',
};

export default ProductCard;
