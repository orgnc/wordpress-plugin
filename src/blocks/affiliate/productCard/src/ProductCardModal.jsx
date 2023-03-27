import PropTypes from 'prop-types';

import IntegrationModal from '../../shared/IntegrationModal';

const ProductCarouselModal = ({
  onClose,
  onProductCardSelect,
  productCardCreationURL,
}) => (
  <IntegrationModal
    iframeURL={productCardCreationURL}
    integrationMessageType="organic/affiliate-select-product-card"
    onClose={onClose}
    onIntegrationSelect={onProductCardSelect}
    title="Product Card Creation"
  />
);

ProductCarouselModal.propTypes = {
  onClose: PropTypes.func.isRequired,
  onProductCardSelect: PropTypes.func.isRequired,
  productCardCreationURL: PropTypes.string.isRequired,
};

export default ProductCarouselModal;
