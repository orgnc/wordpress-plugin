import PropTypes from 'prop-types';

import IntegrationModal from '../components/IntegrationModal';

const ProductSearchModal = ({ onClose, onProductSelect, productSearchPageUrl }) => {
  return (
    <IntegrationModal
      iframeURL={productSearchPageUrl}
      integrationMessageType="organic/affiliate-select-product"
      onClose={onClose}
      onIntegrationSelect={onProductSelect}
      title="Product Search"
    />
  );
};

const noop = () => null;

ProductSearchModal.propTypes = {
  onClose: PropTypes.func,
  onProductSelect: PropTypes.func,
  productSearchPageUrl: PropTypes.string.isRequired,
};

ProductSearchModal.defaultProps = {
  onClose: noop,
  onProductSelect: noop,
};

export default ProductSearchModal;
