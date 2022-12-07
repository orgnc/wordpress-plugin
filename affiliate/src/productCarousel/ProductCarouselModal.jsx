import PropTypes from 'prop-types';

import IntegrationModal from './components/IntegrationModal';

const ProductCarouselModal = ({
  onClose,
  onCarouselSelect,
  productCarouselCreationURL,
}) => (
  <IntegrationModal
    iframeURL={productCarouselCreationURL}
    integrationMessageType="organic/affiliate-product-carousel"
    onClose={onClose}
    onIntegrationSelect={onCarouselSelect}
    title="Product Carousel Creation"
  />
);

ProductCarouselModal.propTypes = {
  onClose: PropTypes.func.isRequired,
  onCarouselSelect: PropTypes.func.isRequired,
  productCarouselCreationURL: PropTypes.string.isRequired,
};

export default ProductCarouselModal;
