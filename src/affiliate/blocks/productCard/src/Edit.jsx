import { BlockControls, useBlockProps } from '@wordpress/block-editor';
import {
  Card,
  CardBody,
  CardHeader,
  IconButton,
  Toolbar,
} from '@wordpress/components';
import {
  createRef,
  useCallback,
  useState,
} from '@wordpress/element';
import PropTypes from 'prop-types';

import ProductCard from './ProductCard';
import ProductCardModal from './ProductCardModal';
import { AttributesType } from './propTypes';

const Edit = ({ attributes, setAttributes, productCardCreationURL }) => {
  const productCardRef = createRef();

  const [showModal, setShowModal] = useState(!attributes.productCardSnippet);
  const hideModal = useCallback(
    () => setShowModal(false),
    [setShowModal],
  );
  const displayModal = useCallback(
    () => setShowModal(true),
    [setShowModal],
  );

  // format of arguments is based on data received from postMessage event in orgnc/platform file IntegrationProductCard.tsx
  const onProductCardSelect = useCallback(
    ({
      productCardSnippet,
      productCardEditURL,
    }) => {
      setAttributes({ productCardSnippet, productCardEditURL });
      hideModal();
      if (productCardRef.current) {
        productCardRef.current.removeAttribute('data-organic-affiliate-processed');
      }
      window.empire?.apps?.affiliate?.init?.();
    },
    [hideModal, productCardRef, setAttributes],
  );

  return (
  // eslint-disable-next-line react/jsx-props-no-spreading
    <div {...useBlockProps()}>
      {showModal && (
        <ProductCardModal
          onClose={hideModal}
          onProductCardSelect={onProductCardSelect}
          productCardCreationURL={attributes.productCardEditURL || productCardCreationURL}
        />
      )}
      <BlockControls>
        <Toolbar>
          <IconButton
            icon="edit"
            label="Edit product card"
            onClick={displayModal}
          />
        </Toolbar>
      </BlockControls>
      <Card>
        <CardHeader>
          Product Card
        </CardHeader>
        <CardBody>
          {attributes.productCardSnippet ? (
            <ProductCard
              ref={productCardRef}
              productCardSnippet={attributes.productCardSnippet}
            />
          ) : (
            <h3>Product Card is unfinished.</h3>
          )}
        </CardBody>
      </Card>
    </div>
  );
};

Edit.propTypes = {
  attributes: AttributesType.isRequired,
  setAttributes: PropTypes.func.isRequired,
  productCardCreationURL: PropTypes.string.isRequired,
};

export default Edit;
