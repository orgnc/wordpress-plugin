import {
  useBlockProps,
  BlockControls,
  InspectorControls,
} from '@wordpress/block-editor';
import {
  Card,
  CardBody,
  CardDivider,
  CheckboxControl,
  Toolbar,
  IconButton,
  PanelBody,
  PanelRow,
  ColorPicker,
} from '@wordpress/components';
import {
  createRef,
  useState,
  useEffect,
  useCallback,
} from '@wordpress/element';
import PropTypes from 'prop-types';

import ProductSearchModal from '../ProductSearchModal';
import ProductCard from './ProductCard';
import { AttributesType } from './propTypes';

const Edit = ({ attributes, setAttributes, productSearchPageUrl }) => {
  const productCardRef = createRef();
  useEffect(() => {
    if (attributes.productGuid) {
      if (productCardRef.current) {
        productCardRef.current.removeAttribute('data-organic-affiliate-processed');
      }
      window.empire?.apps?.affiliate?.init?.();
    }
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [
    attributes.productGuid,
    attributes.displayImage,
    attributes.displayDescription,
    attributes.cardRadius,
    attributes.cardShadow,
    attributes.textColor,
    attributes.linkColor,
    attributes.backgroundColor,
  ]);
  const [showModal, setShowModal] = useState(!attributes.productGuid);
  const hideModal = useCallback(
    () => setShowModal(false),
    [setShowModal],
  );
  const displayModal = useCallback(
    () => setShowModal(true),
    [setShowModal],
  );
  const onProductSelect = useCallback(
    (newProduct) => {
      setAttributes({ productGuid: newProduct.guid });
      hideModal();
    },
    [setAttributes, hideModal],
  );
  const setCardRadius = useCallback(
    (cardRadius) => setAttributes({ cardRadius }),
    [setAttributes],
  );
  const setCardShadow = useCallback(
    (cardShadow) => setAttributes({ cardShadow }),
    [setAttributes],
  );
  const setTextColor = useCallback(
    (textColor) => setAttributes({ textColor }),
    [setAttributes],
  );
  const setLinkColor = useCallback(
    (linkColor) => setAttributes({ linkColor }),
    [setAttributes],
  );
  const setBackgroundColor = useCallback(
    (backgroundColor) => setAttributes({ backgroundColor }),
    [setAttributes],
  );
  const setDisplayImage = useCallback(
    (displayImage) => setAttributes({ displayImage }),
    [setAttributes],
  );
  const setDisplayDescription = useCallback(
    (displayDescription) => setAttributes({ displayDescription }),
    [setAttributes],
  );
  return (
    // eslint-disable-next-line react/jsx-props-no-spreading
    <div {...useBlockProps()}>
      <InspectorControls>
        <PanelBody title="Settings">
          <PanelRow>
            <CheckboxControl
              checked={attributes.cardRadius}
              help="Enable card radius"
              label="Card Radius"
              onChange={setCardRadius}
            />
          </PanelRow>
          <PanelRow>
            <CheckboxControl
              checked={attributes.cardShadow}
              help="Enable card shadow"
              label="Card Shadow"
              onChange={setCardShadow}
            />
          </PanelRow>
        </PanelBody>
        <PanelBody
          initialOpen={false}
          title="Colors"
        >
          <PanelRow>
            Text Color:
          </PanelRow>
          <PanelRow>
            <ColorPicker
              color={attributes.textColor}
              onChange={setTextColor}
            />
          </PanelRow>
          <PanelRow>
            Link Color:
          </PanelRow>
          <PanelRow>
            <ColorPicker
              color={attributes.linkColor}
              onChange={setLinkColor}
            />
          </PanelRow>
          <PanelRow>
            Background Color:
          </PanelRow>
          <PanelRow>
            <ColorPicker
              color={attributes.backgroundColor}
              onChange={setBackgroundColor}
            />
          </PanelRow>
        </PanelBody>
      </InspectorControls>
      {showModal
        && (
        <ProductSearchModal
          onClose={hideModal}
          onProductSelect={onProductSelect}
          productSearchPageUrl={productSearchPageUrl}
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
        {attributes.productGuid ? (
          <>
            <CardBody>
              <h4>Options</h4>
              <CheckboxControl
                checked={attributes.displayImage}
                help="Whether or not the product image should be displayed"
                label="Display Image"
                onChange={setDisplayImage}
              />
              <CheckboxControl
                checked={attributes.displayDescription}
                help="Whether or not the product description should be displayed"
                label="Display Description"
                onChange={setDisplayDescription}
              />
            </CardBody>
            <CardDivider />
            <CardBody>
              <ProductCard
                ref={productCardRef}
                backgroundColor={attributes.backgroundColor}
                cardRadius={attributes.cardRadius}
                cardShadow={attributes.cardShadow}
                displayDescription={attributes.displayDescription}
                displayImage={attributes.displayImage}
                linkColor={attributes.linkColor}
                productGuid={attributes.productGuid}
                textColor={attributes.textColor}
              />
            </CardBody>
          </>
        ) : (
          <CardBody>
            <h3>Product is not selected</h3>
          </CardBody>
        )}
      </Card>
    </div>
  );
};

Edit.propTypes = {
  attributes: AttributesType.isRequired,
  setAttributes: PropTypes.func.isRequired,
  productSearchPageUrl: PropTypes.string.isRequired,
};

export default Edit;
