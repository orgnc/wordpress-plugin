/* eslint-disable react/jsx-props-no-spreading */
import { BlockControls } from '@wordpress/block-editor';
import { ToolbarDropdownMenu } from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import {
  toggleFormat,
  registerFormatType,
  removeFormat,
  create,
  insert,
  applyFormat,
} from '@wordpress/rich-text';
import PropTypes from 'prop-types';

import OrganicIcon from '../../shared/OrganicIcon';
import ProductSearchModal from '../src/ProductSearchModal';

const FORMAT_NAME = 'organic/affiliate-product-link';

const InsertAffiliateLink = ({
  isActive, onChange, value, productSearchPageUrl,
}) => {
  const [showModal, setShowModal] = useState(false);
  const hideModal = useCallback(
    () => setShowModal(false),
    [setShowModal],
  );
  const removeLink = useCallback(
    () => {
      onChange(removeFormat(value, FORMAT_NAME));
    },
    [onChange, value],
  );
  const displayModal = useCallback(
    () => setShowModal(true),
    [setShowModal],
  );
  // format of arguments is based on data received from postMessage event in orgnc/platform file IntegrationProductSearch.jsx
  const onProductSelect = useCallback(({ product, offerUrl }) => {
    const { start, end } = value;
    const format = {
      type: FORMAT_NAME,
      attributes: { url: offerUrl },
    };
    let newValue;
    if (start === end) {
      // If there is no selection then insert product name and add the link
      const productName = create({ text: product.name });
      newValue = insert(
        value,
        applyFormat(productName, format, 0, product.name.length),
        start,
        end,
      );
    } else {
      newValue = toggleFormat(value, format);
    }
    onChange(newValue);
    hideModal();
  }, [value, onChange, hideModal]);
  return (
    <>
      <BlockControls group="other">
        <ToolbarDropdownMenu
          controls={[
            {
              title: isActive ? 'Remove Affiliate Link' : 'Insert Affiliate Link',
              icon: 'admin-links',
              onClick: isActive ? removeLink : displayModal,
            },
          ]}
          icon={OrganicIcon}
          label="Organic Tools"
        />
      </BlockControls>
      {showModal
        && (
        <ProductSearchModal
          onClose={hideModal}
          onProductSelect={onProductSelect}
          productSearchPageUrl={productSearchPageUrl}
        />
        )}
    </>
  );
};

InsertAffiliateLink.propTypes = {
  onChange: PropTypes.func.isRequired,
  value: PropTypes.shape({
    start: PropTypes.number,
    end: PropTypes.number,
  }).isRequired,
  isActive: PropTypes.bool.isRequired,
  productSearchPageUrl: PropTypes.string.isRequired,
};

const register = (productSearchPageUrl) => registerFormatType(FORMAT_NAME, {
  title: 'Affiliate Product Link',
  tagName: 'a',
  attributes: { url: 'href' },
  className: 'organic-affiliate-link',
  edit: (props) => (
    <InsertAffiliateLink
      {...props}
      productSearchPageUrl={productSearchPageUrl}
    />
  ),
});

export {
  register,
};
