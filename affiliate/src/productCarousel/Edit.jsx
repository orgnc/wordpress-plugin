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

import ProductCarousel from './ProductCarousel';
import ProductCarouselModal from './ProductCarouselModal';
import { AttributesType } from './propTypes';

const Edit = ({ attributes, setAttributes, productCarouselCreationURL }) => {
  const productCarouselRef = createRef();

  const [showModal, setShowModal] = useState(false);
  const hideModal = useCallback(
    () => setShowModal(false),
    [setShowModal],
  );
  const displayModal = useCallback(
    () => setShowModal(true),
    [setShowModal],
  );

  const onCarouselSelect = useCallback(
    ({
      productCarouselSnippet,
      productCarouselEditURL,
    }) => {
      setAttributes({ productCarouselSnippet, productCarouselEditURL });
      hideModal();
      if (productCarouselRef.current) {
        productCarouselRef.current.removeAttribute('data-organic-affiliate-processed');
      }
      window.empire?.apps?.affiliate?.init?.();
    },
    [hideModal, productCarouselRef, setAttributes],
  );

  return (
    // eslint-disable-next-line react/jsx-props-no-spreading
    <div {...useBlockProps()}>
      {showModal && (
        <ProductCarouselModal
          onCarouselSelect={onCarouselSelect}
          onClose={hideModal}
          productCarouselCreationURL={attributes.productCarouselEditURL || productCarouselCreationURL}
        />
      )}
      <BlockControls>
        <Toolbar>
          <IconButton
            icon="search"
            label="Select a product"
            onClick={displayModal}
          />
        </Toolbar>
      </BlockControls>
      <Card>
        <CardHeader>
          Product Carousel
        </CardHeader>
        <CardBody>
          {attributes.productCarouselSnippet ? (
            <ProductCarousel
              ref={productCarouselRef}
              productCarouselSnippet={attributes.productCarouselSnippet}
            />
          ) : (
            <h3>Product Carousel is not created</h3>
          )}
        </CardBody>
      </Card>
    </div>
  );
};

Edit.propTypes = {
  attributes: AttributesType.isRequired,
  setAttributes: PropTypes.func.isRequired,
  productCarouselCreationURL: PropTypes.string.isRequired,
};

export default Edit;
