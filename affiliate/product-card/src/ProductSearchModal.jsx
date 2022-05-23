import { Modal } from '@wordpress/components';
import { useEffect } from '@wordpress/element';
import PropTypes from 'prop-types';

const ProductSearchModal = ({ onClose, onProductSelect }) => {
  useEffect(() => {
    const handler = (event) => {
      const data = JSON.parse(event.data);
      if (data?.type === 'organic/affiliate-select-product') {
        onProductSelect(data.product);
      }
    };
    window.addEventListener('message', handler);
    return () => window.removeEventListener('message', handler);
  }, [onProductSelect]);
  return (
    <Modal
      onRequestClose={onClose}
      shouldCloseOnClickOutside={false}
      title="Product Search"
      isFullScreen
    >
      {/* TODO rkashapov: change to product-search page */ }
      <iframe
        srcDoc={`
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Document</title>
</head>
<body>
  <script>
    function selectProduct(data) {
      const message = {
          type: 'organic/affiliate-select-product',
          product: data,
      };
      window.parent.postMessage(JSON.stringify(message), '*');
    }
  </script>
  <button onclick="selectProduct({guid: '964805c4-6bf7-4418-a112-a58f1565a72d'})">Select Product - 1</button>
  <button onclick="selectProduct({guid: '23276d4a-436c-435b-9713-04946785c749'})">Select Product - 2</button>
  <button onclick="selectProduct({guid: 'e48dc9bc-cdc1-4656-9031-891bf6a688cb'})">Select Product - 3</button>
</body>
</html>
        `}
        title="Product Search"
      />
    </Modal>
  );
};

const noop = () => null;

ProductSearchModal.propTypes = {
  onClose: PropTypes.func,
  onProductSelect: PropTypes.func,
};

ProductSearchModal.defaultProps = {
  onClose: noop,
  onProductSelect: noop,
};

export default ProductSearchModal;
