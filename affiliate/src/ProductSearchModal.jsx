import './productSearchModal.scss';

import { Modal } from '@wordpress/components';
import { useEffect } from '@wordpress/element';
import PropTypes from 'prop-types';

const ProductSearchModal = ({ onClose, onProductSelect, productSearchPageUrl }) => {
  useEffect(() => {
    const handler = (event) => {
      const data = JSON.parse(event.data);
      if (data?.type === 'organic/affiliate-select-product') {
        onProductSelect(data.product, data.offerUrl);
      }
    };
    window.addEventListener('message', handler);
    return () => window.removeEventListener('message', handler);
  }, [onProductSelect]);
  return (
    <Modal
      className="product-search-modal"
      onRequestClose={onClose}
      shouldCloseOnClickOutside={false}
      title="Product Search"
      isFullScreen
    >
      {/* TODO rkashapov: change to product-search page */ }
      <iframe
        height="100%"
        src={productSearchPageUrl}
        title="Product Search"
        width="100%"
      />
    </Modal>
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
